# Changelog

## Unreleased

- `xwp_create_app()` and `xwp_app()` now return an `XWP\DI\App` wrapper. Use
  `container()` for the underlying container, `run()` to start the app, and
  `started()` to inspect its lifecycle. Existing container method calls are
  forwarded by the wrapper.
- Application startup and the started state now belong to `App`.
  `Container::run()` and `started()` delegate to the same application.
- `xwp_load_app()` still returns a boolean and defers application creation and
  startup until its configured hook, defaulting to `plugins_loaded`.
- Added internal root-module composition and the `xwp.app` / `App::class`
  application binding, with support for compiled containers and hook caches.
  Existing `app.*` definitions and user-module initialization timing are retained.
