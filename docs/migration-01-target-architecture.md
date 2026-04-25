# Migration 01 - Target Architecture

> The desired Definition -> Compilation -> Dispatch split, with concrete boundaries.

## Overview

```
User code
  #[Module] #[Handler] #[Filter] #[Action] ...
        |
        v
Definition layer
  immutable module/handler/callback/service definitions
        |
        v
Compilation layer
  Parser reflects attributes and static config
  Compiler writes primitive hook-definition.php arrays
        |
        v
Dispatch layer
  Invoker starts the app
  Dispatcher binds and invokes WordPress callbacks
```

`Invoker` remains the bootstrap orchestrator. `Dispatcher` owns hook binding and callback
invocation. `Factory` and `Parser` move away from mutable decorator instances and toward
definition objects, and should be treated as major refactor points.

## Definition layer

Definitions are immutable value objects. They are internal data contracts unless explicitly listed
in `migration-03-public-api.md`.

### `ModuleDefinition`

Fields:

- `class`: root module class-string.
- `hook`: WordPress hook that initializes the module.
- `priority`: hook priority as declared by the module metadata.
- `context`: context bitmask.
- `imports`: list of module class-strings.
- `handlers`: list of handler class-strings.
- `services`: list of service IDs or class-strings.
- `extendable`: whether extension modules may be added through the existing extension mechanism.

### `HandlerDefinition`

Fields:

- `class`: handler class-string.
- `tag`: initialization hook, or empty/null when initialization is immediate/user-driven.
- `priority`: initialization priority.
- `context`: context bitmask.
- `strategy`: one of the `INIT_*` string constants.
- `hookable`: whether method callbacks are automatically registered.
- `params`: resolved `Infuse` metadata for initialization methods.
- `callbacks`: callback definition IDs, populated by parsing method attributes.

### `CallbackDefinition`

Fields:

- `id`: stable callback ID, derived from handler class, method, hook type, and tag.
- `handler`: handler class-string.
- `method`: reflected method name.
- `type`: `action`, `filter`, `rest`, `ajax`, `cli`, or a specific internal type.
- `tag`: final hook tag template before dynamic modifier resolution.
- `priority`: declared priority value.
- `accepted_args`: WordPress accepted argument count.
- `context`: context bitmask.
- `invoke`: invocation bitmask using `INV_*` constants.
- `params`: extra callback params such as `!self.handler`, container IDs, constants, or literal values.
- `modifiers`: dynamic tag modifiers.
- `conditional`: conditional callback metadata, if any.

### `ServiceDefinition`

Fields:

- `id`: service ID.
- `class`: class-string for autowired services, when applicable.
- `kind`: `autowire`, `factory`, or `value`.
- `value`: primitive value or PHP-DI compatible definition metadata.
- `public`: whether module exports expose the service to imports.

2.0 should only implement service shapes already needed by current module/service declarations.
Factory-provider ergonomics can be added later when real usage needs them.

## Compilation layer

`Hook\Parser` reads module attributes and static module configuration. It should produce definition
objects and should not return live decorator instances as its public output.

`Hook\Compiler` serializes definitions to one primitive PHP array file per app:

```php
return array(
    'modules' => array(
        'My\\App\\App_Module' => array(
            'class' => 'My\\App\\App_Module',
            'imports' => array('My\\App\\Admin_Module'),
            'handlers' => array('My\\App\\Foo_Handler'),
            'services' => array('My\\App\\Bar_Service'),
        ),
    ),
    'handlers' => array(
        'My\\App\\Foo_Handler' => array(
            'class' => 'My\\App\\Foo_Handler',
            'tag' => 'init',
            'priority' => 10,
            'strategy' => 'deferred',
            'callbacks' => array('My\\App\\Foo_Handler::on_init[action:init]'),
        ),
    ),
    'callbacks' => array(
        'My\\App\\Foo_Handler::on_init[action:init]' => array(
            'handler' => 'My\\App\\Foo_Handler',
            'method' => 'on_init',
            'type' => 'action',
            'tag' => 'init',
            'priority' => 10,
            'accepted_args' => 0,
            'invoke' => 1,
        ),
    ),
    'services' => array(),
);
```

Cache output must not contain objects, closures, live containers, decorator constructor calls, or
request-specific state.

## Dispatch layer

`Hook\Dispatcher` is constructed with the app container and a primitive/definition graph. It owns:

- Binding callbacks to WordPress with `add_action()`, `add_filter()`, REST, AJAX, and CLI adapters.
- Resolving dynamic tags and priorities at bind time when those values depend on WP/container data.
- Context checks and conditionals.
- Handler initialization strategies: early, now, lazy, JIT, user, and deferred.
- Invocation flags: standard, proxied, safely, looped, and once.
- Runtime state such as fired counts and currently-firing guards.

Decorators no longer own invocation state once the dispatcher path is complete.

## Decorators

Decorators remain user-facing PHP attributes, but their target role is metadata only:

- Constructor arguments remain the user-facing syntax.
- No live container references.
- No target handler instances.
- No runtime `invoke()` path.
- No fluent mutation as part of normal operation.

Removing mutators is intentionally late in the plan. The parser and dispatcher must stop depending
on them first.

## Compatibility stance

v2 is allowed to break v1 internals, but normal attribute users should see a small migration:
composer constraint, PHP floor, config-key cleanup where needed, and removal of calls into internal
decorator/Invoker APIs.
