# Migration 01 — Target Architecture

> Definition, discovery/compilation, and runtime execution, with the current beta transition made explicit.

## Implemented boundary

The [definition split plan](definition-split-plan.md) supersedes the original central `Hook\Dispatcher` proposal. There is one `Hook\Callback` runtime (or specialized subclass) per built-in callback token, and separate `Hook\Handler` runtimes for built-in handler and module tokens. `Invoker` remains the module and handler lifecycle coordinator.

```text
Attribute constructor metadata
          │
          ▼
Discovery → Definitions → cached metadata
          │                   │
          └───── Factory ──────┘
                    │
                    ▼
             Hook runtimes
                    │
                    ▼
             Invoker attaches
                    │
                    ▼
             WordPress hooks
```

F1–F6 complete the metadata/runtime boundary. Built-in and custom metadata attributes use definitions and separate runtimes. Removed custom execution overrides fail with migration guidance. Parser/Compiler definition-graph and cache-schema changes remain separate work.

## Layer 1: Definitions (`src/Definition/`)

`ModuleDefinition`, `HandlerDefinition`, `CallbackDefinition`, and `ServiceDefinition` exist as value objects. They hold metadata without runtime containers, handler instances, or invocation counters.

`CallbackDefinition::from_data()` converts callback metadata arrays. It preserves the callback token, raw priority, tag modifiers, condition, invocation flags, and explicit parameters. It does not execute conditions or resolve runtime values. Factory uses this conversion in both cached and uncached paths.

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

Discovery reads unbound built-in declarations into definitions. Parser traverses these definitions and custom-attribute metadata to build the existing raw metadata/PHP-DI definitions. Compiler writes Parser's raw output to `cache_dir/hook-definition.php` using `var_export()` and reloads it through Parser. The callback split did not change that cache format or invalidation policy.

The plain callback wire format remains `type`, `args`, and `params`; `params` includes the handler classname and method. The callback token resolves through a Factory definition in the cached path. Runtime discovery stores the corresponding runtime under the same token.

A fully typed Parser output and redesigned primitive cache schema remain B2.1/B2.2. They were not prerequisites for the completed plain callback split. Do not treat the earlier proposed module/hooks/services cache sketch as the current wire format.

### Factory

Factory converts callback metadata into `Callback` or specialized runtime subclasses when a container is available. It also builds handler and module runtimes from definitions, including without a container for imperative handler helpers. Custom metadata subclasses use the corresponding runtime family. A callback runtime requires a container; Factory never reconstructs a decorator service.

`resolve_callbacks()` returns built-in callback definitions and custom decorators for discovery and serialization. Once the application has started, `get_callbacks()` returns the stored runtime objects. `load_callbacks()` accepts existing runtimes without applying decorator mutators, and repeated saves preserve existing token entries and their state.

## Layer 3: Runtime (`Hook\Callback` and `Invoker`)

`Callback` receives a `CallbackDefinition` and `Container`. It owns attachment and invocation state, resolves priorities and parameters, checks callback eligibility, and invokes the bound handler instance through the container when proxied.

WordPress callable identity is explicit:

- Standard callbacks register `array( $handler_instance, $method )`.
- Proxied callbacks register `array( $callback, 'invoke' )`, where `$callback` is the object stored under the callback token.
- Action invocation through the runtime returns null. Once, loop-prevention, and safe-exception flags retain their existing semantics.

The `!self.hook` parameter supplies the owning Callback runtime itself. State references, direct invocation, and the container token refer to one object. WordPress removal uses `$hook->target`. Replace injected decorator type hints with runtime types; see [migration notes](decorator-compatibility.md).

Runtime reflection has not been eliminated. For example, `Callback::get_num_args()` falls back to method reflection when the definition omits the accepted argument count. Uncached discovery and container autowiring also retain reflection paths. No zero-reflection performance claim follows from this split.

### Application and handler lifecycle

`xwp_create_app()` and `xwp_app()` return `App`. Creation builds the container; `App::run()` starts root-module registration through Invoker. `xwp_load_app()` schedules creation and startup on its configured WordPress hook.

Invoker retains strategy-specific orchestration. LAZY callbacks request handler initialization during attachment; JIT callbacks attach a proxy and request initialization at invocation. A rejected initialization can be retried. Successful initialization and callback attachment remain separate state.

Module services and static configuration are collected independently of runtime eligibility. Module context gates runtime activation and cascades through its handlers, callbacks, and imports. `can_initialize()` is evaluated at the initialization point, before asynchronous configuration and descendants are activated. Imported modules retain their own scheduling. The caller remains responsible for choosing hooks that will occur after registration. The [lifecycle contract](definition-split-plan.md#agreed-lifecycle) specifies the full strategy matrix.

## Metadata-only decorators

Decorators retain constructor metadata, constants, and declaration exports. They have no container/handler bindings, runtime interfaces, mutators, or invocation state. Custom constructors and `get_declaration()` can describe metadata; custom execution behavior moves to runtime or target handler code. `Infuse` exports injection tokens without resolving them.

F1–F4 ported specialized callbacks, F5 extracted handlers/modules, and F6 removed the temporary decorator runtime and typed-view adapters.

## What the split provides

- Definitions can be inspected independently of invocation state.
- Plain callbacks have one runtime owner and a stable removable WordPress callable.
- Runtime behavior and Factory wiring are tested separately, including cold/warm hook caches and compiled containers.
- Existing bootstrap, container, hook-token, and scheduling contracts remain the basis for later ports.

It does not introduce a new event bus, change WordPress hook ordering, implement module encapsulation, or replace PHP-DI's container compilation.
