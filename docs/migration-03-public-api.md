# Migration 03 - Public API Inventory

> Current intended public surface for v2. This is an inventory before the `2.0.0-rc.1` lock.

## Lock status

The public API is not frozen at the historical `v2.0.0-beta.1` tag. It freezes at `2.0.0-rc.1`
after the dogfood port passes. Until then, changes to this document are allowed when they correct
repo facts or dogfood findings.

Anything not listed here should be marked `@internal` during implementation, unless the dogfood
port proves it must be public.

## Global bootstrap functions

These are global Composer-loaded functions.

| Function | Intended signature | Role |
|---|---|---|
| `xwp_load_app` | `(array $config, string $hook = 'plugins_loaded', int $priority = PHP_INT_MIN): bool` | Schedule app creation and `run()` on a WP hook. |
| `xwp_create_app` | `(array $config): Container` | Create an app container synchronously. |
| `xwp_app` | `(string $container_id): Container` | Fetch a public container by ID. |
| `xwp_has` | `(string $container_id): bool` | Check whether a container exists. |
| `xwp_extend_app` | `(array $extension, string $application): void` | Register an extension module for an app. |
| `xwp_decompile_app` | `(string $container_id, bool $immediately = false): void` | Clear compiled artifacts for an app. |
| `xwp_uninstall_ext` | `(): void` | Extension uninstall cleanup hook. |

## Global handler helper functions

| Function | Intended signature | Role |
|---|---|---|
| `xwp_create_hook_handler` | `(object $instance, string $app): Can_Handle` | Create a runtime handler for a live instance. |
| `xwp_load_hook_handler` | `(object $instance, string $app): Can_Handle` | Create and load a runtime handler for a live instance. |
| `xwp_load_handler_cbs` | `(Can_Handle $handler, array $callbacks): Can_Handle` | Attach pre-built callbacks to a handler. |
| `xwp_register_hook_handler` | `(Can_Handle $handler): void` | Register a handler with its app runtime. |
| `xwp_log` | `(string $message, string|array $vars = array()): void` | Debug logging helper; candidate for internal/deprecated status before RC. |

## Global definition helper functions

Master already exposes WordPress-aware PHP-DI helpers. They should remain documented unless a
separate compatibility decision removes them.

| Function | Role |
|---|---|
| `XWP\DI\option` | PHP-DI helper resolving a WordPress option. |
| `XWP\DI\filtered` | PHP-DI helper resolving a filtered value. |
| `XWP\DI\transient` | PHP-DI helper resolving a WordPress transient. |
| `XWP\DI\module` | Planned v2 helper for `ModuleDefinitionHelper`. |

`wrapped()` exists as a helper class concept on master, but no public global function is currently
present. Do not document it as public unless the function is added intentionally.

## App config keys

Required or expected keys:

```php
array(
    'app_id' => 'my-app',
    'app_module' => My\App\Module::class,
    'app_file' => __FILE__,
    'cache_app' => false,
    'cache_defs' => false,
    'cache_hooks' => false,
    'cache_dir' => WP_CONTENT_DIR . '/cache/xwp-di/my-app',
    'use_attributes' => true,
    'use_autowiring' => true,
    'use_proxies' => false,
    'extendable' => true,
    'public' => true,
)
```

Legacy aliases such as `id`, `module`, `compile`, `compile_dir`, `attributes`, `autowiring`, and
`proxies` may be removed before RC if the migration guide calls that out.

## Decorators

The user-facing attribute names remain public:

- `Module`
- `Handler`
- `Filter`
- `Action`
- `Ajax_Action`
- `Ajax_Handler`
- `CLI_Command`
- `CLI_Handler`
- `REST_Route`
- `REST_Handler`
- `Dynamic_Action`
- `Dynamic_Filter`
- `Infuse`

Constructor argument compatibility is part of the public surface. Runtime mutators and invocation
methods are not intended public API, even though current interfaces expose some of them.

## Interfaces

Pre-RC status:

- `Can_Initialize`, `On_Initialize`, `Has_Context`, and marker interfaces are intended public or
  semi-public contracts.
- `Can_Hook`, `Can_Invoke`, and `Can_Handle` currently mix metadata and runtime execution methods.
  They must be revised or split before RC. Do not freeze their current method sets.
- Any new dispatcher-only execution interface should be internal unless dogfood proves consumers
  need it.

## Constants

These values are part of compatibility and must not change accidentally.

### Context bitmasks

- `CTX_FRONTEND = 1`
- `CTX_ADMIN = 2`
- `CTX_AJAX = 4`
- `CTX_CRON = 8`
- `CTX_REST = 16`
- `CTX_CLI = 32`
- `CTX_GLOBAL = 63`

### Handler initialization strategies

These are string constants:

- `INIT_EARLY = 'early'`
- `INIT_NOW = 'immediately'`
- `INIT_LAZY = 'on-demand'`
- `INIT_JIT = 'just-in-time'`
- `INIT_AUTO = 'deferred'`
- `INIT_USER = 'dynamically'`

Deprecated aliases such as `INIT_IMMEDIATELY`, `INIT_ON_DEMAND`, `INIT_JUST_IN_TIME`,
`INIT_DYNAMICALY`, and `INIT_DEFFERED` must be explicitly kept or removed before RC.

### Invocation bitmasks

- `INV_STANDARD = 1`
- `INV_PROXIED = 2`
- `INV_ONCE = 4`
- `INV_LOOPED = 8`
- `INV_SAFELY = 16`

## Container

Public app-facing methods:

- `run(): static`
- `started(): bool`
- `register(object $instance): Can_Handle`
- `hookOn(object $handler): void`

PHP-DI `get`, `has`, `make`, and `call` remain available through inheritance.

## Internal by default

Unless promoted before RC, these are internal:

- `App_Factory`
- `App_Builder`
- `Invoker`
- `Hook\Parser`
- `Hook\Compiler`
- `Hook\Factory`
- `Hook\Dispatcher`
- `Compiled_Container`
- `Utils\Reflection`
- Traits
- Abstract decorator bases
