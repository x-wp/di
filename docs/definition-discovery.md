# Definition discovery

`Hook\Discovery` reads built-in attribute declarations into `HandlerDefinition` and `CallbackDefinition`. Attribute constructors still validate arguments and normalize defaults, but discovery does not bind these declarations with `with_reflector()`, `with_handler()`, `with_callbacks()`, or `with_cache()`.

The internal `get_declaration()` export contains constructor metadata only. Discovery combines it with the concrete handler classname, reflected method name and argument count, lazy invocation policy, REST registration settings, and initializer injection tokens. Priorities, conditions, dynamic providers, and handler instances remain unresolved during built-in metadata discovery.

Parser traverses handler definitions and uses `ModuleDefinition` for composition. When callbacks are preloaded, it creates a replacement handler definition containing their tokens. The framework root is built from metadata directly. Definitions serialize back to the existing `type` / `args` / `params` arrays; hook tokens, cache layout, and compiler behavior remain unchanged.

Factory uses the same discovery path for runtime registration. With a container, built-in handler definitions become handler runtimes; discovered callback definitions are saved as runtimes under their existing tokens. Rediscovery returns metadata while preserving stored runtime identity. Supplied handler instances are attached directly to a handler runtime. Containerless `make()` and imperative handler helpers retain their legacy reconstruction behavior for compatibility.

## Custom attributes

A custom handler/module attribute, or a class containing custom callback or Infuse attributes, retains legacy handler discovery. This preserves constructor compatibility arguments, shared handler identity, overridden strategy checks, and custom metadata mutations before Parser serializes the handler. Built-in callbacks on such handlers still produce callback definitions; custom callbacks retain their original wiring and execution path.

Custom declaration detection inspects attribute types without constructing the custom method attributes early. Custom Infuse evaluation remains at the legacy serialization/runtime point. Explicitly supplied handler definitions can also use a shared compatibility adapter for custom callback binding.

## Remaining boundary

This completes built-in discovery's separation from decorator mutators. It does not remove the mutator APIs or change typed `!self.hook` views. `Compatibility` still supports custom extensions and imperative legacy reconstruction. A fully attribute-free reflection parser, a new cache schema, and strict immutable decorators remain separate work.
