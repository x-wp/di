# Metadata-only decorators and the F6 migration

F6 completes the attribute/runtime separation. Decorators describe configuration through constructors and `get_declaration()`; they do not hold containers, reflection bindings, handler instances, registration state, or invocation counters. Their `with_*()`, `load()`, `invoke()`, and `can_load()` APIs are removed. Runtime services live in `XWP\DI\Hook` and consume definitions.

## Callback injection

`!self.hook` returns the callback runtime itself, the same object stored under the callback token. Replace injected `Decorators\Filter` or `Decorators\Action` parameter types with `Hook\Callback`, or the appropriate specialized callback runtime. Retained references see live `fired`/`firing` state. Direct `invoke()` calls execute that runtime. Use `$hook->target` for WordPress removal: standard callbacks can still use the target handler method, while proxies use the runtime callable.

`!self.handler` continues to return a runtime implementing `Can_Handle` (or the specialized handler contracts). Attribute type hints are not runtime contracts.

## Custom attributes

Metadata-only subclasses remain supported. A custom attribute can normalize constructor arguments or override `get_declaration()` to describe configuration. Custom `Infuse` declarations export tokens through `get_tokens($handler_token)`. Discovery builds definitions and chooses the corresponding runtime using the attribute family; a custom attribute is never registered as a runtime service.

Legacy overrides of runtime operations, wiring mutators, and bound serialization fail during discovery or cache restoration with a migration error. Move eligibility into declared conditions, application behavior into the target handler, and explicit execution customization into a runtime subclass. Custom `Infuse::get()`/`resolve()` implementations must export tokens through `get_tokens()` instead. Metadata export must not snapshot request-dependent eligibility into the shared cache.

## Imperative registration

Supply handler instances through the existing helpers; their external initialization and identity are preserved. Supply callback runtime objects through `xwp_load_handler_cbs()`. Construct those runtimes from `CallbackDefinition` and the application's container, or use the internal Factory's metadata conversion when working inside the library. A chained `new Filter(...)->with_handler(...)->with_reflector(...)` is no longer supported. Existing supplied runtime objects keep their listener identity and state.

## Compatibility scope

Attribute names, constructor arguments, constants, callback tokens, and the `type` / `args` / `params` cache metadata layout remain. Existing built-in cache metadata is consumed by runtime factories. Caches describing removed custom runtime overrides must be rebuilt after migrating those declarations. F6 does not introduce a new compiler schema or change application startup timing.

The downstream survey recorded Extremis Core's custom `Theme_Module` on a `^1.5.3` dependency. That source snapshot does not establish use of this beta. Any downstream upgrade is a separate package change; it does not require retaining decorators as services in v2.
