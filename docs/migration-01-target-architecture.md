# Migration 01 — Target Architecture

> The three-layer split: definition, compilation, dispatch. Concrete classes and namespaces.

## The shape

```
┌─────────────────────────────────────────────────────────┐
│                       USER CODE                          │
│  #[Module(...)]  #[Handler(...)]  #[Filter(...)]         │
│  PHP attributes on user classes — read-only metadata     │
└──────────────────────────┬──────────────────────────────┘
                           │ reflected once at build time
                           ▼
┌─────────────────────────────────────────────────────────┐
│              LAYER 1: DEFINITION                         │
│  src/Definition/                                         │
│    HookDefinition (interface)                            │
│    ModuleDefinitionHelper                                │
│    HandlerDefinition                                     │
│    CallbackDefinition                                    │
│    ServiceDefinition                                     │
│  Pure value objects. Immutable. Serializable. No WP.     │
└──────────────────────────┬──────────────────────────────┘
                           │ produced by
                           ▼
┌─────────────────────────────────────────────────────────┐
│              LAYER 2: COMPILATION                        │
│  src/Hook/                                               │
│    Parser    — walks module tree, reflects, emits defs   │
│    Compiler  — serializes def graph to primitive arrays  │
│    Factory   — instantiates handlers from definitions    │
│  Reflection happens HERE. Once. Cached to disk.          │
└──────────────────────────┬──────────────────────────────┘
                           │ consumed by
                           ▼
┌─────────────────────────────────────────────────────────┐
│              LAYER 3: DISPATCH                           │
│  src/Hook/Dispatcher.php (new)                           │
│    Consumes definition graph                             │
│    Registers WP hooks via add_action / add_filter        │
│    Handles invocation, context checks, init strategies   │
│  Zero reflection. Zero `with_*()` mutation.              │
└──────────────────────────┬──────────────────────────────┘
                           │ wires into
                           ▼
                  WordPress hook system
```

## Layer 1: Definition (`src/Definition/`)

Pure value objects. Constructed once with all required data. Immutable thereafter. Composable into PHP-DI's existing definition system.

### `HookDefinition` (interface)

```php
namespace XWP\DI\Definition;

interface HookDefinition {
    public function metatype(): string;      // class-string of the runtime wrapper
}
```

Common contract for anything that describes a WP hook attachment. The `metatype()` method names the runtime class that knows how to dispatch this hook (e.g. `Hook\Action`, `Hook\Filter` — the runtime classes, not the decorators).

### `ModuleDefinitionHelper`

```php
namespace XWP\DI\Definition\Helper;

class ModuleDefinitionHelper extends \DI\Definition\Helper\AutowireDefinitionHelper
    implements HookDefinition
{
    public function __construct(string $module);
    public function metatype(): string;
    public function imports(string ...$modules): self;
    public function provides(string ...$services): self;
    public function exports(string ...$services): self;
}
```

PHP-DI compatible. Modules become container definitions, not "decorated handler classes that also import other handlers." The shape mirrors NestJS's `@Module({ imports, providers, exports })` — adapted to PHP-DI's fluent definition idiom.

### `HandlerDefinition` / `CallbackDefinition` / `ServiceDefinition`

Value objects describing what currently lives mutably on `Handler`, `Filter`, etc. After the refactor, the runtime classes hold a reference to their definition; they don't store the data themselves.

## Layer 2: Compilation (`src/Hook/`)

Existing files refactored. Same names, same general flow, different output shape.

### `Parser` (refactored)

Reflection-driven. Walks the module tree starting from `app_module`, finds attributes, produces `HookDefinition[]` / `ModuleDefinition[]` / `ServiceDefinition[]`. Output is plain arrays of definition objects (or, after `Compiler`, plain arrays of primitive data).

The current `Parser` already emits arrays via `Hook::get_data()` — the refactor consolidates that into a typed Definition output and removes the parallel "decorator instances" path.

### `Compiler` (refactored)

Serializes the definition graph to a single PHP file per app: `cache_dir/hook-definition.php`. Output is primitive arrays — no `var_export()` of objects, no decorator constructor calls in the cache file.

Cache format sketch:
```php
return [
    'modules' => [
        'My\\App\\Module' => [
            'imports' => ['My\\App\\Sub_Module'],
            'handlers' => ['My\\App\\Foo_Handler'],
            'services' => ['My\\App\\Bar_Service'],
        ],
    ],
    'hooks' => [
        'My\\App\\Foo_Handler::on_init' => [
            'type' => 'action',
            'tag' => 'init',
            'priority' => 10,
            'args' => 1,
            'context' => CTX_GLOBAL,
        ],
    ],
    'services' => [...],
];
```

Stable across decorator constructor changes. Inspectable by humans. Safe to ship.

### `Factory` (existing, light cleanup)

Stays roughly as-is. Instantiates the runtime hook objects (Hook\Action, Hook\Filter) from definitions when needed.

## Layer 3: Dispatch (`src/Hook/Dispatcher.php`, new)

The class that didn't exist before. Consumes the compiled definition graph. Registers WP hooks. Handles invocation.

Sketch:
```php
namespace XWP\DI\Hook;

final class Dispatcher {
    public function __construct(
        private readonly Container $container,
        private readonly array $definitions, // from Compiler output
    ) {}

    public function bind_all(): void {
        foreach ($this->definitions['hooks'] as $id => $def) {
            \add_filter(
                $def['tag'],
                fn(...$args) => $this->invoke($id, $def, $args),
                $def['priority'],
                $def['args'],
            );
        }
    }

    private function invoke(string $id, array $def, array $args): mixed {
        // context check, init strategy, container resolve, method call
    }
}
```

The decorators no longer have `invoke()` methods. They're metadata. The dispatcher is the only thing WordPress sees.

## Decorators (after the split)

Decorators stay in `src/Decorators/`. They still carry the user-friendly attribute syntax. But they shrink:

- No `with_*()` setters
- No `invoke()`, `load()`, `can_load()` methods
- No reference to handler instances
- No reference to containers
- Constructor arguments only

What remains is the metadata that PHP captures from the attribute literal. Everything else moved to Definition + Dispatcher.

## NestJS analogues (for orientation)

| NestJS | xwp/di v2.0 | Notes |
|--------|-------------|-------|
| `@Module({...})` | `#[Module(...)]` + `ModuleDefinitionHelper` | Same role, PHP-DI definition under the hood |
| `imports: [...]` | `ModuleDefinitionHelper::imports(...)` | Module composition |
| `providers: [...]` | `ModuleDefinitionHelper::provides(...)` | Service registration |
| `exports: [...]` | `ModuleDefinitionHelper::exports(...)` | Public surface of a module |
| `@Injectable()` | (none — autowiring handles it) | PHP-DI autowiring is closer to constructor injection in modern frameworks |
| `useFactory` | PHP-DI `\DI\factory(...)` | Already supported |
| `forRoot()` / `forFeature()` | (deferred to 3.0) | Needs PHP 8.5 closures-in-attributes |
| Guards / interceptors / pipes | (out of scope) | WordPress hooks already handle the cross-cutting concerns |
| `OnModuleInit` lifecycle | `#[Handler]` on a `plugins_loaded` hook | Use WP's lifecycle, don't build a parallel one |

## What this gets us

- **Reflection happens once.** Cached to disk. Production cost is zero.
- **Decorators are testable.** Construct one with literal arguments, assert on its properties. No App_Factory, no WP globals.
- **Dispatcher is testable.** Pass it a fake definition array, fire a closure, assert on side effects.
- **Modules are first-class DI citizens.** They compose via PHP-DI definitions, which is the same machinery that handles every other service.
- **The runtime is replaceable.** If someone wants to write a new dispatcher (compiled hook closures, async runtime, whatever), they consume the same definition graph. The other layers don't change.

## What this does *not* get us

- Lower memory footprint per request — PHP-DI's container is still the same size.
- Faster `add_action`/`add_filter` calls — those are WordPress's bottleneck, not ours.
- A simpler API — the public surface looks the same to users, by design.

The win is structural, not headline-numeric. It compounds: every future change is smaller because the layers don't bleed.
