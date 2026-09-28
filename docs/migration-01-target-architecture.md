# Migration 01 — Target Architecture

> Definition, discovery/compilation, and runtime execution, with the current beta transition made explicit.

## Implemented boundary

The [definition split plan](definition-split-plan.md) supersedes the original central `Hook\Dispatcher` proposal. There is one `Hook\Callback` runtime (or specialized subclass) per built-in callback token, and separate `Hook\Handler` runtimes for built-in handler and module tokens. `Invoker` remains the module and handler lifecycle coordinator.

```text
PHP attributes on modules, handlers, and methods
    │
    ▼
Hook\Parser + reflection ──→ Hook\Compiler / hook-definition.php
    │                              │
    └──────── existing metadata ───┘
                    │
                    ▼
               Hook\Factory
                    │
       ┌────────────┴─────────────────────┐
       ▼                                  ▼
Exact built-in attributes          Custom decorator subclasses
Definitions → Hook runtimes        Compatibility runtime
       │                                  │
       └────────── Invoker attaches ──────┘
                    │
                    ▼
             WordPress hooks
```

This is the current transition, not the completed metadata-only architecture. F1–F5 callback and handler/module ports are implemented. F6 inherited API removal remains open; compatibility implementation is isolated in `src/Compatibility/`. Parser/Compiler definition-graph changes are separate work.

## Layer 1: Definitions (`src/Definition/`)

`ModuleDefinition`, `HandlerDefinition`, `CallbackDefinition`, and `ServiceDefinition` exist as value objects. They hold metadata without runtime containers, handler instances, or invocation counters.

`CallbackDefinition::from_data()` converts existing built-in callback `get_data()` arrays. It preserves the callback token, raw priority, tag modifiers, condition, invocation flags, and explicit parameters. It does not execute conditions or resolve runtime values. Factory uses this conversion in both cached and uncached paths.

The helper contract lives in `XWP\DI\Definition\Helper`, not directly in `XWP\DI\Definition`:

```php
interface HookDefinition {
    public function metatype( string $metatype ): self;
}
```

`ModuleDefinitionHelper` extends PHP-DI's `AutowireDefinitionHelper`. Its `metatype()`, `imports()`, `handlers()`, and `services()` methods configure construction of a `ModuleDefinition`. The public `XWP\DI\module()` helper exposes this composition API. Its existence does not mean Parser already consumes a complete typed definition graph; that integration remains separate work.

The container remains flat: imported modules do not introduce service visibility barriers. Module-level encapsulation stays deferred.

## Layer 2: Discovery and compilation (`src/Hook/`)

### Parser and Compiler

Parser discovers attributes and builds the existing raw metadata/PHP-DI definitions. Compiler writes Parser's raw output to `cache_dir/hook-definition.php` using `var_export()` and reloads it through Parser. The callback split did not change that cache format or invalidation policy.

The plain callback wire format remains `type`, `args`, and `params`; `params` includes the handler classname and method. The callback token resolves through a Factory definition in the cached path. Runtime discovery stores the corresponding runtime under the same token.

A fully typed Parser output and redesigned primitive cache schema remain B2.1/B2.2. They were not prerequisites for the completed plain callback split. Do not treat the earlier proposed module/hooks/services cache sketch as the current wire format.

### Factory

Factory converts exact built-in callbacks into `Callback` or specialized runtime subclasses when a container is available. It also builds handler and module runtimes from definitions. Custom attribute subclasses retain their existing decorator runtime. A containerless Factory can still reconstruct decorator metadata.

`resolve_callbacks()` returns decorators for discovery and serialization. Once the application has started, `get_callbacks()` returns the stored runtime objects. `load_callbacks()` accepts existing runtimes without applying decorator mutators, and repeated saves preserve existing token entries and their state.

## Layer 3: Runtime (`Hook\Callback` and `Invoker`)

`Callback` receives a `CallbackDefinition` and `Container`. It owns attachment and invocation state, resolves priorities and parameters, checks callback eligibility, and invokes the bound handler instance through the container when proxied.

WordPress callable identity is explicit:

- Standard callbacks register `array( $handler_instance, $method )`.
- Proxied callbacks register `array( $callback, 'invoke' )`, where `$callback` is the object stored under the callback token.
- Action invocation through the runtime returns null. Once, loop-prevention, and safe-exception flags retain their existing semantics.

The `!self.hook` parameter supplies a memoized typed Action/Filter view. Its state and runtime calls forward to the owning Callback. The view is a different object from the container entry; removal uses `$hook->target` or the container runtime's callable. Direct `$hook->invoke()` still works. See [migration compatibility notes](migration-05-deprecation-and-shipping.md#current-beta-callback-split).

Runtime reflection has not been eliminated. For example, `Callback::get_num_args()` falls back to method reflection when the definition omits the accepted argument count. Uncached discovery and container autowiring also retain reflection paths. No zero-reflection performance claim follows from this split.

### Application and handler lifecycle

`xwp_create_app()` and `xwp_app()` return `App`. Creation builds the container; `App::run()` starts root-module registration through Invoker. `xwp_load_app()` schedules creation and startup on its configured WordPress hook.

Invoker retains strategy-specific orchestration. LAZY callbacks request handler initialization during attachment; JIT callbacks attach a proxy and request initialization at invocation. A rejected initialization can be retried. Successful initialization and callback attachment remain separate state.

Module services and static configuration are collected independently of runtime eligibility. Module context gates runtime activation and cascades through its handlers, callbacks, and imports. `can_initialize()` is evaluated at the initialization point, before asynchronous configuration and descendants are activated. Imported modules retain their own scheduling. The caller remains responsible for choosing hooks that will occur after registration. The [lifecycle contract](definition-split-plan.md#agreed-lifecycle) specifies the full strategy matrix.

## Decorators: retained now, reduced later

The intended end state is metadata-only attribute declarations. Current decorators still have runtime methods and internal `with_*()` mutators because custom subclasses, discovery wiring, and typed forwarding views depend on them. Their shared implementation lives in internal `Compatibility` adapters.

F1–F4 have ported Dynamic, AJAX, REST, and CLI callbacks. F5 has extracted handler/module runtime. F6 removes obsolete decorator behavior only after the custom-subclass migration policy and typed-view dependencies are settled. Removing those methods now would break the supported transition.

## What the split provides

- Definitions can be inspected independently of invocation state.
- Plain callbacks have one runtime owner and a stable removable WordPress callable.
- Runtime behavior and Factory wiring are tested separately, including cold/warm hook caches and compiled containers.
- Existing bootstrap, container, hook-token, and scheduling contracts remain the basis for later ports.

It does not introduce a new event bus, change WordPress hook ordering, implement module encapsulation, or replace PHP-DI's container compilation.
