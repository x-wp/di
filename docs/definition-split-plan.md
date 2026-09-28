# Definition Split Plan — Callbacks

> Split `#[Filter]` / `#[Action]` metadata from runtime behavior. Callback container tokens resolve to a runtime `Hook\Callback` built from a `CallbackDefinition`, instead of to the decorator instance. Spec first, slice plan after.

## Relation to the migration docs

- Pulls [migration-04](migration-04-implementation-plan.md) **B3.2** forward for plain `Filter` / `Action`. It does not wait on B2.1 (Parser output), B2.2 (Compiler format) or B3.1 (strip `with_*()`).
- Replaces the central `Hook\Dispatcher` sketch in [migration-01](migration-01-target-architecture.md) §Layer 3 with **one runtime object per callback**, for three reasons:
  - The WordPress callable stays stable, so `remove_filter()` keeps working. Dispatcher closures would break it.
  - Per-callback state (`fired`, `firing`, `loaded`) has an owner.
  - The object *is* the container entry for the callback token. Nothing new has to be looked up.
- Slice S4 updates migration-01 and migration-04 to point here.

## Goal

Decorators describe, `Callback` runs.

Done when:

1. A method decorated with plain `#[Filter]` or `#[Action]` is registered and fired by `XWP\DI\Hook\Callback`, never by `Filter::load()` / `Filter::invoke()`.
2. Nothing user-visible changes. See the [compatibility checklist](#compatibility-checklist).
3. `Dynamic_Filter`, `Dynamic_Action`, `Ajax_Action`, `REST_Route` and `CLI_Command` keep running on the legacy decorator runtime, untouched.

## Non-goals

- Handler / Module split. Outlined as follow-up F5 only.
- Porting the `Filter` subclasses (F1–F4).
- Removing `with_*()`, `invoke()`, `load()` from decorators (B3.1, follow-up F6).
- Changing Parser or Compiler output. The hook cache file format is unchanged.
- New public API. Everything added here is `@internal`.

## Current flow (the seam)

Callbacks reach the container along two producer paths, and are consumed in one place.

**Producer A: cached / preloaded.**
[Parser::add_hook()](../src/Hook/Parser.php) stores `$hook->get_data()` under `{token}[params]` and registers `{token}` as
`\DI\factory( array( Factory::class, 'make' ) )->parameter( 'hook', \DI\get( '{token}[params]' ) )`.
[Factory::make()](../src/Hook/Factory.php) rebuilds the decorator from that array: `new $hook['type']( ...$hook['args'] )`, then `with_data( $hook['params'] )`.

**Producer B: runtime (hook cache off, or user-registered instances).**
`Invoker::register_methods()` → `Factory::resolve_callbacks()` → `resolve_method_callbacks()` → `save_hook()` → `$container->set( $token, $decorator )`.

**Consumer.**
The handler only keeps tokens (`with_callbacks( $tokens )`).
[Invoker::invoke_methods()](../src/Invoker.php) does `get_hook( $token )->load()`, then `add_callback()` reads `get_method()`, `get_tag()`, `get_classname()`, `is_loaded()`, `get_init_hook()`.

**The token is the seam.** Change what `{token}` resolves to and neither the handler nor the Invoker needs to know.

## Design

### 1. Definition — `CallbackDefinition::from_data()`

The decorator already emits its definition: `Filter::get_data()` is the hook cache wire format, and it is fully populated in both producer paths.

Add a named constructor instead of a new decorator method:

```php
public static function from_data( array $data ): self;
```

| `get_data()` key | `CallbackDefinition` field | Notes |
|---|---|---|
| `type` | `type` | `Filter::class` → `'filter'`, `Action::class` → `'action'` |
| `params.classname` | `handler` | |
| `params.method` | `method` | |
| `args.tag` | `tag` | Raw template, braces already stripped by `Hook::__construct()` |
| `args.priority` | `priority` | **Raw**, see below |
| `args.args` | `accepted_args` | Already resolved from reflection by `with_reflector()` |
| `args.context` | `context` | |
| `args.invoke` | `invoke` | May already contain the lazy-handler flip; the flip is idempotent |
| `args.params` | `params` | |
| `args.modifiers` | `modifiers` | Raw, resolved at runtime |
| `args.conditional` | `conditional` | |
| — | `id` | Computed; must equal the decorator's `get_token()` byte for byte |

**Decision: `from_data()` rather than `Filter::define()`.**

- It works identically in both producer paths.
- Existing `hook-definition.php` cache files stay valid without regeneration.
- It adds nothing to the decorator that B3.1 would then have to strip.

**Change: widen `priority`.**
`CallbackDefinition::$priority` is `int` today. The decorator accepts an int, a constant name, a `filter_name:default` string or a callable, and resolves it at runtime (`Hook_Invoke_Methods::resolve_priority()`). The definition must hold the raw value:
`null|\Closure|string|int|array $priority = 10`.

**ID rule.**
Mirror `Hook::generate_token()` for callbacks:

```text
trim( 'Hook-' . trim( $classname, '-' ) . '::' . ltrim( "{$method}[{$tag}]", '-' ), '-:/' )
```

Token parity is a hard requirement, enforced by tests (S1).

### 2. Runtime — `XWP\DI\Hook\Callback`

`@internal`, **not** `final`. The F1–F4 ports subclass it.

```php
class Callback {
    public function __construct(
        protected CallbackDefinition $definition,
        protected Container $container,
    );
}
```

**State:** `fired`, `firing`, `loaded`, `init_hook`, the lazily resolved `handler`, the effective `invoke` flags, and the raw `tag`. The raw `tag` is kept because `Hook_Invoke_Methods` reads `$this->tag`.

**Public surface** (what `Invoker`, `Factory` and WordPress call):

| Method | Ported from |
|---|---|
| `load(): bool` | `Filter::load()` |
| `invoke( mixed ...$args ): mixed` | `Filter::invoke()`; returns `null` when `type === 'action'` (was `Action::invoke()`) |
| `can_load(): bool` | `Filter::can_load()` + `Hook::can_load()` |
| `is_loaded()`, `get_init_hook()` | `Hook` |
| `get_token()` | Returns `$definition->get_id()` |
| `get_classname()`, `get_method()` | Definition |
| `get_tag()`, `get_priority()` | `Hook::get_tag()` / `get_priority()`, resolved at runtime |
| `get_num_args()` | Definition. Falls back to reflecting `[ classname, method ]` once if `null`. |
| `get_handler(): Can_Handle` | `Filter::get_handler()`: `$container->get( 'Hook-' . $classname )` |
| `get_definition()`, `get_container()` | New, trivial |
| `__get()` | Mirrors `Hook::__get()` for the `Can_Invoke` `@property-read` names: `tag`, `fired`, `firing`, `method`, `target` |

**Protected seams keep the `Filter` names**, so the subclass ports stay mechanical: `load_hook()`, `fire_hook()`, `get_cb_args()`, `get_cb_arg()`, `resolve_tag()`, `init_handler()`, `cb_valid()`, `get_target()`, `handle_exception()`, `current()`.

**Action vs filter** comes only from `$definition->get_type()`:

- registration uses `add_{type}`
- `current()` uses `current_{type}`
- `invoke()` returns `null` for actions

**Shared logic** comes from `use Hook_Invoke_Methods;`: priority and tag resolution, and conditional checks. Relax that trait's `@phpstan-require-implements Can_Hook` so `Callback` can use it. That is a phpdoc-only change.

**Lazy handler flip.** On the first `get_handler()`, if `$handler->is_lazy()`, apply
`$invoke = ( $invoke | INV_PROXIED ) & ~INV_STANDARD`.
This mirrors `Filter::with_handler()`.

**Handler contract.** `Callback` only uses these handler methods:

- `is_loaded()`
- `is_lazy()`
- `get_strategy()`
- `get_token()`
- `get_target()`

and the handler instance itself for `!self.handler`. F5 must keep exactly this surface.

**What gets registered with WordPress:**

- `INV_STANDARD` on a non-lazy handler registers `[ $handler->get_target(), $method ]`. This is unchanged, so `remove_filter( $tag, [ $instance, 'method' ], $prio )` still works.
- Otherwise it registers `[ $callback, 'invoke' ]`. The callback *is* the container entry for the token, so `remove_filter( $tag, [ $container->get( $token ), 'invoke' ], $prio )` still works.

### 3. Param tokens

| Token | Resolves to |
|---|---|
| `!self.handler` | The handler (unchanged) |
| `!self.hook` | A **decorator view**: a `Filter` or `Action` rebuilt from the definition (constructor args plus `with_classname()`, `with_method()`, `with_container()`), memoized on first use |
| `!value:`, `!global:`, `!const:`, container IDs, literals | Unchanged |

The `!self.hook` view keeps working for the public example [Product_Page_Handler.php](../examples/simple-plugin/src/WC/Handlers/Product_Page_Handler.php): `Action $hook` type-hints and `$hook->tag` behave as before.

**Known difference:** runtime state on the view (`fired`, `firing`, `loaded`) is not live, because the state lives on `Callback`. Record this in the CHANGELOG.

### 4. Wiring

All routing goes through one internal check:

```php
private function is_plain_callback( string $type ): bool {
    return \in_array( $type, array( Filter::class, Action::class ), true );
}
```

It is an exact-class match, so every subclass stays on the legacy path.

| Location | Change |
|---|---|
| `Factory::make( array $hook )` | If `is_plain_callback( $hook['type'] )`, return `new Callback( CallbackDefinition::from_data( $hook ), $this->ctr() )`. Otherwise use the legacy path unchanged. |
| `Factory::save_hook()` | When started and the hook is a plain `Filter`/`Action`, store `new Callback( CallbackDefinition::from_data( $hook->get_data() ), $ctr )` under the token instead of the decorator. It still **returns the decorator** to callers: Parser and `register_methods()` only read `get_token()`. |
| `Factory::get_hook()`, `Factory::get()`, `Factory::load_callbacks()`, `Invoker::add_callback()` | Widen types and phpdoc to `Can_Invoke|Callback`. No logic change. |
| `Parser`, `Compiler`, `Invoker` flow, `Container`, `App_*`, `Handler`, all decorators | **No change.** |

**Container compilation (`cache_app`).**
`Callback` objects are only ever created by the `Factory::make` factory at resolution time, or by `set()` at runtime. No objects end up inside definitions, so the compiled container is unaffected.

**Transitional duplication.**
`Filter` keeps its runtime methods because the subclasses still use them. For plain `Filter`/`Action` those methods become dead paths. They are deleted in F6 (B3.1).

## Compatibility checklist

Each item needs a test in S2 or S3.

- [ ] Callback tokens are byte-identical, including tags with `{}` and modifiers.
- [ ] `has_filter()` / `has_action()` report the same priority, and the same accepted-args count is registered.
- [ ] Priority forms all resolve: int, constant name, `filter:default` string, callable array.
- [ ] `remove_filter()` works with `[ $instance, 'method' ]` (standard) and `[ $container->get( $token ), 'invoke' ]` (proxied).
- [ ] `INV_ONCE`, `INV_LOOPED`, `INV_SAFELY`, `INV_PROXIED` semantics are unchanged.
- [ ] `INV_SAFELY` returns `$args[0]` and logs; without it, the exception is rethrown.
- [ ] For lazy and JIT handlers, `{token}_{strategy}_init` fires exactly as before, and the callback is proxied.
- [ ] Context and `conditional` gating are unchanged.
- [ ] `xwp_di_hooks_loaded_{$classname}` still fires.
- [ ] `Invoker::get_actions()` output is unchanged.
- [ ] An existing `hook-definition.php` cache loads without regeneration.
- [ ] `!self.hook` receives an `Action`/`Filter` with a resolved `tag`; `!self.handler` receives the handler.
- [ ] A `Dynamic_Filter`, `Ajax_Action`, `REST_Route` or `CLI_Command` token still resolves to its decorator.
- [ ] The container still compiles with `cache_app` on.

## Risks

| Risk | Mitigation |
|---|---|
| Token drift orphans handler callback lists | S1 parity tests against `get_token()` on real decorators |
| `!self.hook` state is no longer live | Documented; state was never part of the documented contract |
| Two runtimes exist during the transition | Exact-class routing; F6 deletes the legacy one |
| `save_hook()` returns a different object than it stores | Callers only read `get_token()`; covered by the S3 runtime-path test |

## Testing

- **Unit** (`tests/Unit/Definition/CallbackDefinition_Test.php`):
  - `from_data()` maps every field.
  - Priority stays raw.
  - Token parity for a plain tag, a tag with `{}`, and a tag with modifiers.
- **Integration** (`tests/Integration/`, needs WP):
  - A `Callback_Test` that drives `Callback` directly (S2).
  - A wiring test that boots a fixture app with hook cache on and off and walks the checklist (S3).
- **Fixtures** go under `test/fixtures/shared/`:
  - a handler with plain `#[Filter]`/`#[Action]` covering each `INV_*` flag and `!self.hook`/`!self.handler` params;
  - a lazy handler;
  - one `Dynamic_Filter` method as the legacy-path control.
- **Gates:** `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`, `vendor/bin/phpcs`, and `examples/simple-plugin` still bootstraps.

## Slice plan

Each slice is one bead and one PR.

### S1 — `CallbackDefinition::from_data()` + raw priority

- **Scope:**
  - Add `from_data()`.
  - Widen `$priority` and `get_priority()` to the raw type.
  - Compute the ID with the token rule.
  - *Out:* anything runtime.
- **Files:** `src/Definition/CallbackDefinition.php`, `tests/Unit/Definition/CallbackDefinition_Test.php`.
- **Acceptance:** unit tests green, including token parity against `Filter::get_token()` / `Action::get_token()`. No runtime behavior change.
- **Depends on:** B1.2 (done).

### S2 — `Hook\Callback` runtime + `!self.hook` view

- **Scope:**
  - Add `src/Hook/Callback.php` per Design §2–§3.
  - `use Hook_Invoke_Methods`, and relax its phpstan annotation.
  - *Out:* wiring it into `Factory`.
- **Files:** `src/Hook/Callback.php`, `src/Traits/Hook_Invoke_Methods.php` (phpdoc only), `tests/Integration/Callback_Test.php`, fixtures.
- **Acceptance:**
  - A manually constructed `Callback` passes every behavioral checklist item that does not involve `Factory` or `Invoker`.
  - phpstan and phpcs are clean.
- **Depends on:** S1.

### S3 — Route plain `Filter` / `Action` tokens to `Callback`

- **Scope:**
  - `Factory::is_plain_callback()`.
  - `Factory::make()` and `Factory::save_hook()` routing.
  - Type widening in `Factory` and `Invoker`.
- **Files:** `src/Hook/Factory.php`, `src/Invoker.php`, `tests/Integration/*`.
- **Acceptance:**
  - Every compatibility checklist item has a test, with hook cache both on and off.
  - `$container->get( $token ) instanceof Callback` for plain callbacks; subclass tokens still resolve to their decorators.
  - `examples/simple-plugin` boots.
  - All gates pass.
- **Depends on:** S2.

### S4 — Doc sync

- **Scope:**
  - Point migration-01 §Layer 3 and migration-04 B3.2 at this plan.
  - Record the `!self.hook` note for the CHANGELOG.
- **Depends on:** S3.

### Follow-ups (designed later, not in this plan)

| ID | Work | Legacy methods to move into a `Callback` subclass | Depends on |
|---|---|---|---|
| F1 | Port `Dynamic_Filter` / `Dynamic_Action` | `load_hook`, `get_cb_args`, `invoke` (action) | S3 |
| F2 | Port `Ajax_Action` | `can_load`, `resolve_tag`, `load_hook`, `fire_hook`, `get_cb_args`, guard/nonce/cap checks | S3 |
| F3 | Port `REST_Route` | `invoke`, `get_callback`, `with_handler` | S3 |
| F4 | Port `CLI_Command` | `load_hook`, `get_callback`, `get_priority`, `run_cmd` | S3 |
| F5 | Handler split: `HandlerDefinition::from_data()` plus a runtime handler behind `Hook-{class}` | Keep the handler contract from §2 | S3 |
| F6 | B3.1: strip runtime methods and `with_*()` from `Filter`/`Action` | — | F1–F5 |

```text
S1 ─→ S2 ─→ S3 ─┬─→ S4
                ├─→ F1 ─┐
                ├─→ F2 ─┤
                ├─→ F3 ─┼─→ F6
                ├─→ F4 ─┤
                └─→ F5 ─┘
```
