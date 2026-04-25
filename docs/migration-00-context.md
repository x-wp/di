# Migration 00 - Context

> Why the v2 line exists, what problem it is solving, and what discipline applies before GA.

## The problem

The current runtime works, but the core attribute classes do too many jobs at once. `Filter`,
`Action`, `Handler`, and `Module` are declarative metadata, mutable runtime state, and live
WordPress callbacks. That fusion drives the hard parts of the codebase: request-time reflection,
container lookups from decorators, fluent `with_*()` mutation, and callback behavior that is hard
to test without booting the app runtime.

The fix is structural. Decorators should describe intent. A compiler should turn that intent into
a stable graph. A dispatcher should register and invoke WordPress callbacks.

## Target shape

v2 should converge on three layers:

1. **Definition** - immutable value objects that describe modules, handlers, callbacks, and
   services. No WordPress calls, no container references, no runtime state.
2. **Compilation** - reflection-driven build steps that read attributes and static configuration
   once and produce a definition graph. Cached output is primitive arrays.
3. **Dispatch** - runtime services that consume the graph, register WordPress hooks, initialize
   handlers, and invoke callbacks.

Public usage should remain familiar where possible: PHP attributes on modules/handlers,
`xwp_load_app()` or `xwp_create_app()` for bootstrap, and `xwp_app()` for public container access.

## Versioning reality

The repository already has a historical `v2.0.0-beta.1` tag at commit `2950574`. That tag is not
the future API-lock event described by the first draft of these migration docs. Treat it as a
pre-lock beta snapshot.

The revised lock point is `2.0.0-rc.1`, after the implementation slices and the dogfood plugin
port prove that the public surface is sufficient. Until the RC, beta releases may still correct
the public surface, provided the docs and migration guide are updated in the same change.

## What v2.0 is not

- Not a NestJS runtime port. The module ergonomics are useful; guards, interceptors, pipes,
  request scopes, and custom lifecycle systems are not part of 2.0.
- Not a rewrite of `Container`, `Compiled_Container`, `App_Factory`, or `App_Builder` unless a
  slice proves one of those classes blocks the migration.
- Not a custom event bus. WordPress hooks remain the dispatch substrate.
- Not a PHP 8.5 feature line. The PHP floor is 8.1. Closures-in-attributes and factory providers
  in attributes are deferred.

## Deferred

These are explicitly outside 2.0:

- PHP 8.5 closures-in-attributes.
- NestJS-style guards, interceptors, pipes, exception filters, and request-scoped providers.
- Custom compiled-container formats beyond PHP-DI's container compilation.
- Replacement of WordPress hooks with a custom event bus.
- Dynamic module patterns such as `forRoot()` / `forFeature()`.

## Discipline contract

1. **Truth before lock.** Docs, Beads, and code must agree before public API is frozen.
2. **Dogfood before RC.** One real production plugin must be ported before `2.0.0-rc.1`.
3. **One replacement path.** When a replacement layer is introduced, the old behavior either
   delegates to it or is removed in a bounded follow-up. Do not carry two equal architectures.
4. **Small slices.** If a slice grows beyond its acceptance criteria, file another bead instead of
   expanding the current one.
5. **Tests first for runtime rewrites.** Parser, compiler, dispatcher, and decorator changes need
   focused tests before they are treated as complete.

## Reading order

- [migration-01-target-architecture.md](migration-01-target-architecture.md)
- [migration-02-current-state.md](migration-02-current-state.md)
- [migration-03-public-api.md](migration-03-public-api.md)
- [migration-04-implementation-plan.md](migration-04-implementation-plan.md)
- [migration-05-deprecation-and-shipping.md](migration-05-deprecation-and-shipping.md)
