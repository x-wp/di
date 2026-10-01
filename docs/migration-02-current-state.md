# Migration 02 — Current State of `beta` and the Gap

> Updated after the built-in callback and handler/module ports (S1–S4, F1–F6). This replaces the April 2026 snapshot; use source and beads for subsequent changes.

## Implemented structure

| Area | Current responsibility |
|---|---|
| `App`, `App_Factory`, `App_Builder` | Application wrapper, creation, container building, and startup |
| `Container`, `Compiled_Container` | PHP-DI integration and forwarding to Invoker |
| `Invoker` | Module/handler lifecycle, initialization strategies, callback attachment |
| `Hook/Discovery`, `Hook/Parser`, `Hook/Compiler` | Unbound declaration-to-definition discovery, module traversal, existing hook-cache format |
| `Hook/Factory` | Resolve metadata, handlers, and callback runtimes |
| `Hook/Callback` and specialized subclasses | Registration and execution for callback attribute families |
| `Hook/Handler`, `Hook/Module`, and specialized handlers | Handler initialization and module composition state |
| `Definition/` and `Definition/Helper/` | Module, handler, callback, and service value objects; module composition helper |
| `Decorators/` | Metadata-only attribute declarations |
| `Hook/Hook`, runtime traits | Handler wiring and lifecycle, without attribute inheritance |
| `tests/Unit`, `tests/Integration` | Definition tests and real WordPress lifecycle/cache/runtime coverage |

There is no central `Hook/Dispatcher`. The [definition split plan](definition-split-plan.md) replaces that proposal with one Callback per callback token.

## Completed callback boundary

Factory converts exact `Filter`/`Action` metadata into a `CallbackDefinition` and `Callback`, both when resolving cached metadata and when discovering callbacks after startup. Callback tokens and cache metadata arrays are unchanged. Discovery returns definitions for built-in and custom metadata attributes; legacy execution overrides require migration. Started-app callback lookup returns stored runtime objects. See [definition discovery](definition-discovery.md).

Plain callback execution state belongs to Callback. Standard hooks retain the bound handler-method callable; proxies use the container Callback's `invoke` method. Reloading callbacks preserves runtime identity and counters.

`!self.hook` injects the runtime object itself, identical to the callback token entry. Direct invocation and retained state references use that runtime; WordPress removal uses its registered `$hook->target` callable. See the [F6 migration](decorator-compatibility.md).

F1–F4 cover Dynamic, AJAX, REST, and CLI callbacks; F5 adds separate handler/module runtimes. F6 removes decorator mutators, runtime operations, and typed views. Custom attributes can customize constructor metadata; custom execution behavior belongs in runtime or target handler code.

## Established lifecycle

Application creation and startup are distinct: helpers return `App`, and `App::run()` starts root-module registration through Invoker. `xwp_load_app()` schedules both operations.

Module context gates runtime activation through its descendants. Services and static configuration remain available independently of those runtime gates. Initialization conditions are evaluated at the strategy-specific initialization point. LAZY requests initialization during attachment; JIT does so during invocation. Rejected initialization can retry, and successful initialization retains its state. Late scheduling remains the caller's responsibility.

See [the lifecycle contract](definition-split-plan.md#agreed-lifecycle) and the integration tests for the complete strategy matrix.

## Remaining work

| Area | Remaining boundary |
|---|---|
| Compiler (B2.2) | Primitive-only cache-schema work remains separate; discovery now uses definitions for all supported attributes while retaining the existing cache layout |
| Module composition (B4.1) | Complete helper-driven integration with discovery; helper/value-object existence alone does not establish it |
| Verification and release | Continue focused coverage, dogfood a production plugin, and complete release gates |

These are separate slices, not authorization to start all of them. Beads tracks their execution status.

## Preserve during later ports

- `App` owns startup; do not move lifecycle orchestration into the container.
- Invoker remains the coordinator, with strategy-specific ordering.
- Callback tokens identify runtime objects; do not recreate them when reloading callback lists.
- Parser consumes definitions during discovery, even when Factory stores a runtime under the token.
- Preserve custom metadata subclass support and reject unsupported legacy execution overrides explicitly.
- No blanket zero-reflection claim: uncached discovery, autowiring, and Callback's omitted-argument-count fallback can reflect at runtime.

## Validation

The completed split is covered by `Callback_Runtime_Test`, `Callback_Wiring_Test`, `Self_Hook_Test`, and the existing lifecycle suites. Wiring coverage includes runtime discovery, preloading, hook caches, compiled containers, cold/warm passes, and custom metadata and legacy-override rejection. Example bootstrap and source static checks are part of code-change validation.

Run `composer test:unit`, `composer test:integration`, or `composer test` with their explicit suites. Documentation-only changes are checked against source and links; they do not imply a new test run. Dogfood and release acceptance remain separate from these development checks.
