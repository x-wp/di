# Migration 02 — Current State of `beta` and the Gap

> Updated after the built-in callback and handler/module ports (S1–S3, F1–F5). This replaces the April 2026 snapshot; use source and beads for subsequent changes.

## Implemented structure

| Area | Current responsibility |
|---|---|
| `App`, `App_Factory`, `App_Builder` | Application wrapper, creation, container building, and startup |
| `Container`, `Compiled_Container` | PHP-DI integration and forwarding to Invoker |
| `Invoker` | Module/handler lifecycle, initialization strategies, callback attachment |
| `Hook/Parser`, `Hook/Compiler` | Attribute discovery, metadata definitions, existing hook-cache format |
| `Hook/Factory` | Resolve metadata, handlers, and callback runtimes |
| `Hook/Callback` and specialized subclasses | Registration and execution for exact built-in callback types |
| `Hook/Handler`, `Hook/Module`, and specialized handlers | Handler initialization and module composition state |
| `Definition/` and `Definition/Helper/` | Module, handler, callback, and service value objects; module composition helper |
| `Decorators/` | Attribute declarations, typed callback views, and custom-subclass compatibility |
| `Compatibility/` | Shared legacy wiring and dispatch implementation; inherited mutators remain |
| `tests/Unit`, `tests/Integration` | Definition tests and real WordPress lifecycle/cache/runtime coverage |

There is no central `Hook/Dispatcher`. The [definition split plan](definition-split-plan.md) replaces that proposal with one Callback per callback token.

## Completed callback boundary

Factory converts exact `Filter`/`Action` metadata into a `CallbackDefinition` and `Callback`, both when resolving cached metadata and when discovering callbacks after startup. Callback tokens and cache metadata arrays are unchanged. Discovery still returns decorators for Parser; started-app callback lookup returns stored runtime objects.

Plain callback execution state belongs to Callback. Standard hooks retain the bound handler-method callable; proxies use the container Callback's `invoke` method. Reloading callbacks preserves runtime identity and counters.

`!self.hook` remains a typed Action/Filter view with live forwarded state. It is distinct from the runtime object. Direct invocation forwards, while WordPress removal uses the runtime callable exposed by `$hook->target`. The [migration notes](migration-05-deprecation-and-shipping.md#current-beta-callback-split) describe both identity changes.

F1–F4 extend this boundary to exact Dynamic, AJAX, REST, and CLI callbacks; see [specialized runtime details](specialized-callback-runtime.md). F5 adds separate [handler and module runtimes](handler-module-runtime.md), including specialized handlers. Custom decorator subclasses retain their existing path. F6 preparation isolates inherited implementation in `Compatibility/`; it does not remove mutation or dispatch APIs or make attributes immutable. See [the remaining compatibility boundary](decorator-compatibility.md).

## Established lifecycle

Application creation and startup are distinct: helpers return `App`, and `App::run()` starts root-module registration through Invoker. `xwp_load_app()` schedules both operations.

Module context gates runtime activation through its descendants. Services and static configuration remain available independently of those runtime gates. Initialization conditions are evaluated at the strategy-specific initialization point. LAZY requests initialization during attachment; JIT does so during invocation. Rejected initialization can retry, and successful initialization retains its state. Late scheduling remains the caller's responsibility.

See [the lifecycle contract](definition-split-plan.md#agreed-lifecycle) and the integration tests for the complete strategy matrix.

## Remaining work

| Area | Remaining boundary |
|---|---|
| Decorator cleanup (F6/B3.1) | Complete API removal after custom-subclass/view migration is settled; compatibility implementation is isolated |
| Parser/Compiler (B2.1/B2.2) | Integrate typed definition output and any cache-schema redesign; the current callback split leaves these formats intact |
| Module composition (B4.1) | Complete helper-driven integration with discovery; helper/value-object existence alone does not establish it |
| Verification and release | Continue focused coverage, dogfood a production plugin, and complete release gates |

These are separate slices, not authorization to start all of them. Beads tracks their execution status.

## Preserve during later ports

- `App` owns startup; do not move lifecycle orchestration into the container.
- Invoker remains the coordinator, with strategy-specific ordering.
- Callback tokens identify runtime objects; do not recreate them when reloading callback lists.
- Parser still needs metadata during discovery, even when Factory stores a runtime under the token.
- Specialized and custom subclasses must keep working until their migration is explicitly handled.
- No blanket zero-reflection claim: uncached discovery, autowiring, and Callback's omitted-argument-count fallback can reflect at runtime.

## Validation

The completed split is covered by `Callback_Runtime_Test`, `Callback_Wiring_Test`, `Self_Hook_Test`, and the existing lifecycle suites. Wiring coverage includes runtime discovery, preloading, hook caches, compiled containers, cold/warm passes, and legacy subclass controls. Example bootstrap and source static checks are part of code-change validation.

Run `composer test:unit`, `composer test:integration`, or `composer test` with their explicit suites. Documentation-only changes are checked against source and links; they do not imply a new test run. Dogfood and release acceptance remain separate from these development checks.
