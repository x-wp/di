# Changelog

## Unreleased

- Plain `Filter` and `Action` callback tokens now resolve to `Hook\Callback`,
  built from `CallbackDefinition`. Runtime state and invocation belong to that
  object; hook tokens, cache metadata, and initialization timing are preserved.
  Dynamic, AJAX, REST, and CLI built-ins use specialized Callback runtimes;
  custom decorator subclasses retain their compatibility runtime.
- Built-in handlers and modules now resolve to separate `Hook` runtimes using
  the existing `Can_Handle` interfaces and token identity. Definitions preserve
  unresolved metadata; Invoker retains lifecycle orchestration. See
  [handler migration details](docs/handler-module-runtime.md).
- Legacy decorator wiring and dispatch are isolated in internal `Compatibility`
  adapters. Inherited mutators and custom override behavior remain available;
  immutable decorators and API removal are still pending F6.
- `!self.hook` remains a typed decorator view with live forwarded state and
  direct invocation. It is no longer the object returned by callback-token lookup.
  Remove its WordPress listener using `$hook->target`, or the container Callback's
  `invoke` callable for proxies; `array( $hook, 'invoke' )` names the separate view.
- Callback loading preserves existing runtime objects and invocation counters
  across discovery and reloads, including cached and compiled-container paths.
- Module context gates descendant runtime activation while services and static
  configuration remain available. LAZY attachment and JIT invocation retain
  their initialization timing and retry behavior. See the
  [lifecycle contract](docs/definition-split-plan.md#agreed-lifecycle).

- `xwp_create_app()` and `xwp_app()` now return an `XWP\DI\App` wrapper. Use
  `container()` for the underlying container, `run()` to start the app, and
  `started()` to inspect its lifecycle. Existing container method calls are
  forwarded by the wrapper.
- Application startup and the started state now belong to `App`.
  Use `App::run()` for startup; `Container::started()` reads the same application state.
- `xwp_load_app()` still returns a boolean and defers application creation and
  startup until its configured hook, defaulting to `plugins_loaded`.
- Added internal root-module composition and the `xwp.app` / `App::class`
  application binding, with support for compiled containers and hook caches.
  Existing `app.*` definitions and user-module initialization timing are retained.
