# Migration 02 — Current State of `beta` and the Gap

> A snapshot of `beta` as of 2026-04-23. What's there, what's the gap to the target architecture, what to keep / rework / throw out.

## Snapshot

`beta` is at commit `2950574` (`fix(Core): Fixed ProxyFactory signature on PHP 8.4+`, 2026-04-16). It is *not* what the older `docs/state-rewrite.md` describes. The `rewrite` branch had `old/` + a `Definition/` layer that beta does not. Beta is a maturation of the **alpha-style** parser/compiler architecture — it never carried the rewrite's NestJS-style direction forward.

What `beta` has on disk:

```
src/
  App_Builder.php            ← container builder, configures PHP-DI
  App_Factory.php            ← singleton factory, app lifecycle
  Compiled_Container.php     ← abstract base for compiled containers
  Container.php              ← custom Container, run() bootstrap, __call proxy
  Invoker.php                ← orchestrates handler registration + dispatch
  Decorators/                ← Hook, Handler, Module, Filter, Action, REST_*, AJAX_*, CLI_*, Dynamic_*, Infuse
  Global/                    ← Context, REST_Controller, CLI_Namespace
  Hook/
    Parser.php               ← reflects on modules, builds raw arrays
    Factory.php              ← instantiates handlers/hooks from data
    Compiler.php             ← serializes parser output to cache file
  Interfaces/                ← 15 contract interfaces
  Functions/                 ← public helper functions
  Traits/                    ← Accessible_Hook_Methods, Hook_Invoke_Methods, Hook_Token_Methods
  Utils/Reflection.php       ← reflection helpers
```

What `beta` does **not** have:

- `src/Definition/` directory or any Definition value objects
- `ModuleDefinitionHelper` / `HookDefinition` interface (these existed on `rewrite`, never made the jump)
- `Hook/Dispatcher.php` (the runtime layer that should consume compiled definitions)
- A test directory beyond fixtures

## The gap to target architecture

| Target layer | What's on beta | Gap |
|---|---|---|
| **Definition (Layer 1)** | Nothing — decorator instances act as definitions | Whole layer must be added. Port `ModuleDefinitionHelper` from `rewrite`, fit to beta's class layout. Add `HookDefinition`, `HandlerDefinition`, `CallbackDefinition`, `ServiceDefinition` value objects. |
| **Compilation (Layer 2)** | `Hook/Parser`, `Hook/Compiler`, `Hook/Factory` exist and work | `Parser` emits ad-hoc arrays *and* decorator instances — needs to emit Definition objects only. `Compiler` `var_export()`'s decorator instances — needs to emit primitive arrays. |
| **Dispatch (Layer 3)** | Dispatch logic lives inside `Filter::invoke()`, `Action::invoke()`, etc. Decorators ARE the dispatchers. | Whole layer must be extracted. New `Hook/Dispatcher` class, runtime `invoke()` moves out of decorators. |
| **Decorators** | Mutable. `with_*()` setters, `invoke()` methods, handler/container references | Make immutable. Strip mutators and runtime methods. Keep them as PHP attribute holders only. |

## Salvage list (keep as-is)

These are correct as they stand. Touch only if a refactor forces it.

- `App_Factory` — singleton container factory + lifecycle. Works.
- `App_Builder` — fluent PHP-DI builder. Works.
- `Container` — custom container with `run()`. Works. The 8.4 ProxyFactory fix was real.
- `Compiled_Container` — abstract base for compiled output. Works.
- `Invoker` — orchestrator. Will get *thinner* as Dispatcher takes over runtime, but the orchestration itself stays.
- All interfaces in `src/Interfaces/`. Stable contracts.
- All traits in `src/Traits/`. Stable shared logic.
- `Global/Context`, `Global/REST_Controller`, `Global/CLI_Namespace`. Working WP-facing bases.
- `Functions/xwp-di-container-fns.php`, `Functions/xwp-di-helper-fns.php`. The public API.
- `Utils/Reflection`. Stable.

## Rework list (refactor, don't rewrite)

- `Hook/Parser` — change output type from raw arrays + decorator instances to typed Definition objects. Internal change; same input (module classnames), different output shape.
- `Hook/Compiler` — change serialization format from `var_export()`'d objects to primitive arrays. Cache file becomes stable across decorator constructor changes.
- `Hook/Factory` — light cleanup once Parser produces Definition objects. Stays as the "instantiate runtime hook objects from definitions" service.
- All decorators in `src/Decorators/` — strip `with_*()` mutators and `invoke()`/`load()`/`can_load()` methods. Keep constructor arguments and getters. Public attribute syntax unchanged for users.

## Throw-out list (delete in v2.0)

- `Filter::invoke()`, `Action::invoke()`, `REST_Route::invoke()` — runtime invocation logic. Moves to `Hook/Dispatcher`.
- `Filter::load()`, `Filter::can_load()` — load/conditional checks. Move to `Hook/Dispatcher`.
- `Handler::with_*()`, `Filter::with_*()`, etc. — fluent mutation API. Constructors take all data.
- The `$container_id` / `xwp_app($container_id)` lookup pattern — Dispatcher receives the container by constructor injection, not global lookup.
- Whatever remains of the `parse_legacy_config()` shim in `App_Factory` — v2.0 is a clean break, no v1.x config compat.

## Frenzy detection signals to watch for during the migration

These are the patterns that produced the previous rewrites. If any appears mid-slice, file a follow-up bead and do not extend the current slice:

- A new public class that nobody asked for.
- A second copy of a thing that was supposed to replace the first.
- A new lifecycle hook that is not a WordPress hook.
- An "internal" abstraction with one consumer.
- A NestJS feature being ported because it would be cool, not because a slice needs it.
- A "while I'm in here" cleanup that grows past two files.

## Open architectural questions (resolve during slices, not now)

- **Dispatcher, one per app or one per dispatch event?** Probably one per app, lifetime tied to the container.
- **Do we keep `Invoker` as an orchestration class or fold its responsibilities into `Container::run()`?** Keep it. It works and it's a reasonable seam.
- **Does the compiled cache file become a class or stay a return-array?** Stay a return-array. Less magic, easier to inspect, no opcache class-shape concerns.
- **Does the new `Hook/Dispatcher` register itself with the container as a service, or is it constructed at bootstrap time?** Constructed at bootstrap. It's the entrypoint, not a service.

## Verification before any slice merges

- The existing examples under `examples/` must still bootstrap successfully.
- The hook cache file must be deterministic across runs (same input → same output).
- A handler with at least one `#[Filter]`, one `#[Action]`, one `#[REST_Route]` must register and fire correctly.
- The dogfood plugin port (slice B6.1) must run end-to-end before tagging GA.
