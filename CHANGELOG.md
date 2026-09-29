# Changelog

## Unreleased

### Breaking changes in the beta migration

- `xwp_create_app()` and `xwp_app()` return `App`; use `container()` where a
  container is required. Startup belongs to `App::run()`. Removed container
  methods now throw `BadMethodCallException` instead of silently returning null.
- Exact built-in handler/module tokens, public handler helpers, and
  `!self.handler` expose runtime objects. Use `Can_Handle`, `Can_Import`, or
  specialized handler interfaces instead of concrete decorator parameter types.
- The injected `!self.hook` is a typed view rather than the registered runtime.
  Remove proxied listeners with `$hook->target`; `array( $hook, 'invoke' )` only
  identifies the view. Explicitly supplied decorators retain listener identity.
- Lazy initialization notification tags now include the application UUID to
  prevent one application's callbacks from initializing another's handlers.
  Consumers of these internal tags must use `get_lazy_tag()`.

### Review fixes

- Proxied callback conditions are checked at invocation, so false conditions
  during attachment do not permanently drop callbacks. Direct callbacks retain
  attachment-time checks.
- Preserve live custom `Infuse` overrides, supplied callback identity, narrow
  custom constructors, and one-argument `check_method()` overrides. Injected
  handler tokens keep their declared argument positions.
- Restore CLI helpers, typed hook-view declaration properties, tagless supplied
  handler priorities, and registration-order startup at `PHP_INT_MIN`.
- Wait for supplied `USER` targets, retain initialization during constructor
  self-adoption, attach later callbacks, and defer AUTO module context selection
  until its hook. Repeated callback declarations receive distinct tokens and
  listeners while single-declaration tokens remain compatible.
- Remove the unused, inconsistent `Strategy` enum before release; use `INIT_*`.
- Verify and pin integration downloads, split suite bootstrap and coverage,
  reset REST test state, and gate release on tests, PHPStan and PHPCS.

### Runtime migration

- Built-in discovery now constructs definitions from unbound attribute metadata.
  Parser records built-in callback IDs on definitions; custom declarations retain
  legacy mutator-based wiring. Existing single-declaration cache tokens remain compatible. Custom callback and Infuse
  declarations retain legacy discovery so extension overrides and metadata
  mutations continue to work. See [definition discovery](docs/definition-discovery.md).

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
