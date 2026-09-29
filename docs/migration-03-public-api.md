# Migration 03 — Public API Surface (Locked at 2.0.0-beta.1)

> Every public function, decorator, interface, and helper. From the `2.0.0-beta.1` tag forward, this surface does not grow.

## The lock contract

After `2.0.0-beta.1` is tagged: a change to this surface is one of (a) bug fix in already-promised behavior, (b) pure internal optimization with identical outward behavior, (c) deferred to 3.0. Anything else is scope creep — file a 3.0 bead and move on.

Anything *not* in this document is `@internal`. Internal classes can change without a major version bump. Tag them with `@internal` in PHPDoc so static analysers and IDEs flag misuse.

## Bootstrap functions (`XWP\DI` namespace)

| Function | Signature | Role |
|---|---|---|
| `xwp_load_app` | `(array $config, string $hook = 'plugins_loaded', int $priority = PHP_INT_MIN): bool` | Schedule app creation on a WP hook. Returns true if scheduled. |
| `xwp_create_app` | `(array $config): App` | Synchronous app creation. Returns the application without starting it. |
| `xwp_app` | `(string $container_id): App` | Fetch a registered public application by ID. |
| `xwp_has` | `(string $container_id): bool` | Check whether a container is registered. |
| `xwp_extend_app` | `(array $extension, string $application): void` | Register an extension/feature flag bundle for an existing app. |
| `xwp_decompile_app` | `(string $container_id, bool $immediately = false): void` | Clear compiled artifacts for an app (cache invalidation). |
| `xwp_uninstall_ext` | `(): void` | Hook into WP's uninstall flow to clean up extension state. |

### `$config` array shape

```php
[
    'app_id'          => string,                   // unique identifier (required)
    'app_module'      => class-string,             // root #[Module] class (required)
    'app_file'        => string,                   // plugin's main __FILE__ (required)
    'cache_app'       => bool,                     // compile container (default: production)
    'cache_defs'      => bool,                     // PHP-DI APCu cache (default: production with APCu)
    'cache_hooks'     => bool,                     // hook-definition.php (default: production)
    'cache_dir'       => string,                   // where to write artifacts
    'use_attributes'  => bool,                     // PHP-DI attribute autowiring (default: true)
    'use_autowiring'  => bool,                     // PHP-DI autowiring (default: true)
    'use_proxies'     => bool,                     // lazy proxies (default: false)
    'extendable'      => bool,                     // allow xwp_extend_app (default: true)
]
```

## Handler/hook helper functions

| Function | Signature | Role |
|---|---|---|
| `xwp_create_hook_handler` | `(object $instance, string $app): Can_Handle` | Build a handler runtime from a live instance. |
| `xwp_load_hook_handler` | `(object $instance, string $app): Can_Handle` | Same as `create_hook_handler` but loads it. |
| `xwp_load_handler_cbs` | `(Can_Handle $handler, array $callbacks): Can_Handle` | Attach pre-built callbacks to a handler. |
| `xwp_register_hook_handler` | `(Can_Handle $handler): void` | Push a handler to the invoker. |
| `xwp_log` | `(string $message, string\|array $vars = []): void` | Optional debug logger. May be removed in 3.0. |

## Decorators (`XWP\DI\Decorators\*`)

All are PHP attributes. Metadata-only declarations are the intended end state. Current beta decorators retain internal mutators and legacy runtime behavior for custom subclasses through `Compatibility` adapters. Exact built-in callbacks and handlers resolve to separate `Hook` runtimes. This preparatory cleanup does not make attributes immutable. The typed `!self.hook` view is described in the [migration notes](migration-05-deprecation-and-shipping.md#current-beta-callback-split).

| Decorator | Target | Constructor (key arguments) |
|---|---|---|
| `Module` | class | `imports[]`, `handlers[]`, `services[]`, `hook`, `priority` |
| `Handler` | class | `tag`, `priority`, `context`, `strategy` |
| `Filter` | method | `tag`, `priority`, `args`, `context`, `invoke` |
| `Action` | method | (extends `Filter`, void return) |
| `Ajax_Action` | method | `tag`, `priority`, `nonce`, `prefix` |
| `Ajax_Handler` | class | (extends `Handler`, sets context to AJAX) |
| `CLI_Command` | method | `tag`, ... |
| `CLI_Handler` | class | (extends `Handler`, sets context to CLI) |
| `REST_Route` | method | `route`, `methods`, `vars`, `guard` |
| `REST_Handler` | class | (extends `Handler`, sets context to REST) |
| `Dynamic_Action` | method | `tag` (with `%s` placeholder), `modifiers[]` |
| `Dynamic_Filter` | method | `tag` (with `%s` placeholder), `modifiers[]` |
| `Infuse` | method | ordered service tokens and `!self.handler` for initializer arguments |

## Definition helpers (`XWP\DI\Definition\Helper\*`)

New in v2.0. Compose container definitions declaratively.

| Helper | Role |
|---|---|
| `ModuleDefinitionHelper` | Produces a PHP-DI `ObjectDefinition` for a module class with `imports`, `handlers`, `services` |
| (interface) `HookDefinition` | Common contract for definition helpers that produce hook bindings |

The four definition helpers already on master 1.x (`option()`, `transient()`, `filtered()`, `wrapped()`) are intentionally **not** part of v2.0's surface. They live on master/1.x; if they're wanted in v2.0 they get backported in a 2.1 minor, not v2.0.

## Definition functions (`XWP\DI` namespace)

| Function | Signature | Role |
|---|---|---|
| `module` | `(string $class): ModuleDefinitionHelper` | Sugar for `new ModuleDefinitionHelper($class)` |

(Future: `service()`, `factory_service()`, etc., as 2.x minors. Not in initial 2.0.)

## Interfaces (`XWP\DI\Interfaces\*`)

Stable contracts. Implement to integrate with the library.
Interface names are part of the public surface. Individual methods marked `@internal` in source are runtime wiring for the current beta implementation and are not SemVer-stable.
`Async_Module` currently exists in the global namespace; the rest are under `XWP\DI\Interfaces`.

| Interface | Role |
|---|---|
| `Can_Handle` | A class that can be registered as a hook handler. |
| `Can_Hook` | A method-level hook descriptor. |
| `Can_Import` | A module that can import other modules. |
| `Can_Invoke` | A callback descriptor. |
| `Can_Initialize` | A handler with a `can_initialize()` condition. |
| `Has_Context` | Anything that validates against execution context (REST/CLI/etc.). |
| `On_Initialize` | Lifecycle hook for handler initialization. |
| `Can_Route` | A REST route descriptor. |
| `Can_Handle_Ajax`, `Can_Handle_CLI`, `Can_Handle_REST` | Specialization markers. |
| `Async_Module`, `Extendable_Module`, `Extension_Module` | Module composition markers. |
| `Can_Execute` | Runtime executable contract for CLI decorators during the transition. |

## Constants

| Constant | Range | Role |
|---|---|---|
| `CTX_GLOBAL`, `CTX_FRONTEND`, `CTX_ADMIN`, `CTX_AJAX`, `CTX_REST`, `CTX_CLI`, `CTX_CRON` | bitmask | Execution context flags |
| `INIT_AUTO`, `INIT_EARLY`, `INIT_LAZY`, `INIT_JIT`, `INIT_NOW`, `INIT_USER` | string constants | Handler initialization strategies |
| `INV_STANDARD`, `INV_PROXIED`, `INV_SAFELY`, `INV_LOOPED`, `INV_ONCE` | bitmask | Invocation flags |

These flags exist on master 1.x. v2.0 keeps them. The exact bit values are part of the locked surface.

## Application (`XWP\DI\App`)

`App` wraps the dependency container and owns startup and the started state.
Its `@mixin Container` annotation exposes forwarded container methods to IDEs.
`xwp_create_app()` builds it synchronously;
call it only once class autoloading is safe. `xwp_load_app()` registers a closure
and returns a boolean without loading application classes. By default, that
closure creates and runs the application at `plugins_loaded` with priority `PHP_INT_MIN`. Applications at that priority retain registration order, allowing Jetpack
Autoloader to finish selecting dependency versions first.

| Method | Signature | Role |
|---|---|---|
| `container` | `(): Container` | Access the underlying dependency container. |
| `run` | `(): static` | Start the module lifecycle and return this application. A second call throws. |
| `started` | `(): bool` | Has the application been started? |
| `__call` | `(string $name, array $args): mixed` | Forward container methods, including existing handler helpers. |

`App::class` and `xwp.app` resolve to the same application instance returned by
the bootstrap helpers. The container retains its own PHP-DI and PSR-11 identity.
This also applies when container compilation or hook caching is enabled.

The internal `Core\Modules\Internal_Root_Module` provides framework definitions
and imports the configured user module. It initializes immediately when the app
runs; the user module and its imports retain their declared hook timing.
Existing `app.*` entries and user definition precedence are preserved:
`app.module` still identifies the user module, `app` still resolves its hook
descriptor, and the new internal `app.root` identifies the framework root.
`xwp.app.env` aliases `app.env`.

## Container (`XWP\DI\Container`)

Limited public surface — most methods are `@internal`. Public on `Container`:

| Method | Signature | Role |
|---|---|---|
| `started` | `(): bool` | Read the app's started state. |
| `register` | `(object $instance): Can_Handle` | Register a runtime handler instance. |
| `hookOn` | `(object $handler): void` | Attach a handler to its declared hook. |

Plus PHP-DI's standard container methods (`get`, `has`, `make`, `call`). Startup belongs to `App::run()`; `Container::run()` is no longer supported. Unknown forwarded methods throw `BadMethodCallException` instead of silently returning null.

## WordPress-facing base classes

| Class | Role |
|---|---|
| `XWP_REST_Controller` | Base REST controller that wires `on_initialize()` to route registration. |

The classmapped `XWP_Context` and `XWP_CLI_Namespace` classes are runtime implementation details and are `@internal`.

## What's `@internal`

Anything not in the tables above. Highlights:

- `App_Factory`, `App_Builder` — bootstrap mechanism, not for direct use
- `Invoker` — orchestrator, not for direct use
- `Hook\Parser`, `Hook\Compiler`, `Hook\Factory`, `Hook` runtime classes, and `Compatibility\*` — internal pipeline, execution, and legacy adapters
- `Compiled_Container` — generated, not for human consumption
- `Utils\Reflection`, `Traits\*`, `XWP_Context`, `XWP_CLI_Namespace`
- `Decorators\Hook` and inherited base-class behavior on decorators — extend at your own risk; signatures may change in minors
- Any public method tagged `@internal`, especially decorator mutation/dispatch methods such as `with_*()`, `load()`, `can_load()`, and `invoke()`

If you find yourself reaching for an `@internal` class to do something user-code-shaped, that's a signal we're missing a public helper. File a 2.1 bead.

## SemVer commitments

- **2.0.0** → **2.x.y**: no breaking change to anything in this document. New decorators, new helpers, new interface methods (default-implemented) are minors. New required interface methods are majors.
- **2.x.y** → **2.x.y+1**: bug fixes only. No new public surface.
- **2.0.0-beta.x**: surface may still change between betas, but the *shape* documented here is the target. Major changes require a doc update PR before tagging.
- **3.0.0**: this is the door for everything in the parking lot.

## Versioning of attached docs

This file is itself part of the lock. Changes to it require a separate decision and a CHANGELOG entry. Prefer adding a `migration-03-public-api-2.1.md` for additions over editing this one in-place.
