# Definition Split Plan — Lifecycle and Callbacks

> Establish the module and handler lifecycle, then split `#[Filter]` / `#[Action]` metadata from runtime behavior. Callback container tokens resolve to a runtime `Hook\Callback` built from a `CallbackDefinition`, instead of to the decorator instance. Preserve strategy-specific ordering throughout the split.

## Review scope

This plan targets the `beta` architecture present in this checkout, including `Hook\Parser`, `Hook\Factory`, and the definition value objects. Repository notes describing the lightweight `master` runtime do not describe these producer and consumer paths.

The lifecycle conclusions below extend the original callback-only plan. They distinguish agreed behavior from current implementation findings and decisions still needed before implementation. This document records the design; it does not claim that the runtime already implements it.

## Relation to the migration docs

- Pulls [migration-04](migration-04-implementation-plan.md) **B3.2** forward for plain `Filter` / `Action`. It does not wait on B2.1 (Parser output), B2.2 (Compiler format) or B3.1 (strip `with_*()`).
- Replaces the central `Hook\Dispatcher` sketch in [migration-01](migration-01-target-architecture.md) §Layer 3 with **one runtime object per callback**, for three reasons:
  - The WordPress callable stays stable, so `remove_filter()` keeps working. Dispatcher closures would break it.
  - Per-callback state (`fired`, `firing`, `loaded`) has an owner.
  - The object *is* the container entry for the callback token. Nothing new has to be looked up.
- Slice S4 updates migration-01 and migration-04 to point here.
- Lifecycle slices L1–L2 precede the callback runtime work. They establish the strategy contract and clarify the existing orchestration before behavior moves out of decorators.
- The migration-00 rule against parallel runtimes conflicts with this plan's transitional subclass runtime. S4 must reconcile that rule with the bounded transition and F6 removal requirements.

## Agreed lifecycle

### Modules: unconditional definitions, scheduled execution

Every module contributes its services and definitions to the application regardless of request context. Imports are discovered as part of the complete module graph. Including those declarations does not require constructing every module instance immediately.

The module's hook and priority determine when its initialization runs and when it triggers its handlers. In this discussion, `module_init` names that lifecycle step; the existing implementation uses `on_initialize()`. This plan does not add a new public initialization method.

```text
Build application
  └─ Collect module services, definitions, and imports

Module hook + priority
  └─ Initialize module and finish its initialization callback
      └─ Apply module context to handler registration
          └─ Register eligible handlers according to their strategies
```

Module context controls whether its handlers enter the runtime lifecycle. It does not remove module services or definitions from the container. This is a change from the current inherited `Handler::can_load()` gate, which can prevent the module itself from initializing; it must be implemented and tested explicitly.

Handler context and initialization conditions remain additional checks. A module triggering a handler means registering it with the lifecycle coordinator; construction and callback attachment depend on its strategy.

Imported modules retain their own initialization hooks and priorities. Their definitions are always discovered. The proposed runtime boundary gives each imported module its own handler-registration context rather than inheriting its parent's context; L1 must cover this behavior explicitly.

### Discovery, registration, initialization, attachment, invocation

| Operation | Meaning | Current implementation |
|---|---|---|
| Discover | Read declarations and obtain handler/callback metadata; cacheable | `Parser`, `Factory::resolve_callbacks()`, `Invoker::register_methods()` |
| Register handler | Admit a handler into the app runtime once and arrange its strategy | `Invoker::register_handler()` and `add_handler()` |
| Initialize handler | Check initialization conditions, resolve its instance, configure it, and run `on_initialize()` | `Invoker::init_handler()` and `Handler::load()` |
| Attach callbacks | Register callables with WordPress | `Invoker::invoke_methods()` and `Filter::load()` |
| Invoke callback | Execute the handler method when its hook fires | Direct method callable or `Filter::invoke()` |

`register_handler()` is the strategy coordinator. Its existing `match` already expresses different lifecycle paths and should remain explicit. Registration can immediately cause initialization for `EARLY` or `NOW`; it does not imply that every strategy creates an instance.

Names currently obscure this separation: `register_methods()` discovers callbacks, `invoke_methods()` attaches them, and `load()` means initialization on a handler but attachment on a callback. `Invoker::load_handler($instance)` adopts an existing instance and enters it into registration.

Proposed internal responsibilities are `initialize_handler()`, `ensure_callbacks()`, `attach_callbacks()`, and scheduling helpers under `register_handler()`. These are implementation boundaries, not new public APIs. Keep existing externally consumed entry points compatible.

### Strategy contract

The following describes successful paths in the current orchestration; context and conditions can prevent progress. Callback discovery can use cached metadata or reflection without changing initialization timing.

| Strategy | At `register_handler()` | At the handler's hook | At callback invocation |
|---|---|---|---|
| `AUTO` | Schedule initialization and attachment | Initialize, discover callbacks, attach them | Execute |
| `EARLY` | Initialize; schedule attachment | Discover and attach callbacks | Execute |
| `NOW` | Initialize, discover and attach callbacks | No additional scheduled step | Execute |
| `LAZY` | Install initialization listener; schedule attachment | Discover callbacks; callback loading requests initialization before attachment | Execute |
| `JIT` | Install initialization listener; schedule attachment | Discover and attach proxy callbacks without initializing the handler | Request initialization, then execute if permitted |
| `USER` | Use supplied instance; discover and attach callbacks | No additional scheduled step | Execute |

`LAZY` and `JIT` deliberately share the registration branch. Their distinction happens downstream: callback loading requests `INIT_LAZY`, while callback invocation requests `INIT_JIT`. Both retain the lazy-handler switch to proxied invocation.

Do not move LAZY initialization unconditionally into handler registration. Initialization is requested by callback loading, so a handler with no callbacks does not acquire an initialization attempt merely because it was registered. Preserve this edge case in tests.

### State and conditions

Registration, instance initialization, and callback attachment are separate facts. The coordinator records whether a handler has been registered/scheduled; the handler runtime owns its instance and initialization state; each callback owns attachment and invocation state. A JIT handler can be registered and have attached callbacks while its instance does not yet exist.

- Registration must be idempotent, including installation of scheduling hooks.
- Discovery, cache loading, and runtime callback construction must not accidentally instantiate handlers.
- An initialization condition that returns false must not mark the handler initialized. Preserve subsequent opportunities to attempt initialization; do not cache a request-wide rejection without an explicit policy.
- A JIT handler's initialization condition must not prevent its proxy callbacks from being attached. Evaluate that condition when initialization is attempted during invocation.
- Preserve initialization once it succeeds, including the initialization callback running once.

Context selection and arbitrary conditions have different timing. An unconditional entry is always eligible, but may still have a scheduled or JIT initialization strategy. A known request context can select relevant handlers; conditions remain pending until their lifecycle point. The examples illustrate why: `WC_Module` checks for WooCommerce, `Post_List_Page_Handler` checks the current screen, and the JIT `Product_Page_Handler` checks `is_product()`.

Keep the complete declarations in the shared cache. Do not freeze context selection or condition outcomes from the request that generated it. `XWP_Context` currently memoizes a context and detects REST via the request URI; the timing and completeness of that classification need coverage before using it to discard work permanently at app startup.

### Ordering and remaining decisions

Application bootstrap, module activation, handler scheduling, and callback execution each have their own hook and priority. Preserve those stages. The default handler behavior of using the current action and current priority plus one also belongs in lifecycle coverage.

Before implementing module orchestration in L2, settle these remaining cases:

- Whether module context is a strict gate on its handlers or a default that an explicit handler context can override. The proposed gate model is strict; override behavior has not been agreed.
- How context applies to attributed callback methods on the module itself. Applying the module context to their attachment is the proposed rule.
- What happens when registration occurs after the intended handler hook/priority has already passed. Immediate catch-up versus waiting for another occurrence needs an explicit policy.
- How existing module `can_initialize()` conditions relate to unconditional module initialization. Module definitions remain unconditional either way; module initialization conditions need an explicit migration decision.

## Goal

Decorators describe, `Callback` runs.

Done when:

1. A method decorated with plain `#[Filter]` or `#[Action]` is registered and fired by `XWP\DI\Hook\Callback`, never by `Filter::load()` / `Filter::invoke()`.
2. Callback extraction preserves user-visible behavior. Module lifecycle changes are limited to the agreed contract above and must have explicit coverage. See the [compatibility checklist](#compatibility-checklist).
3. `Dynamic_Filter`, `Dynamic_Action`, `Ajax_Action`, `REST_Route` and `CLI_Command` keep running on the legacy decorator runtime, untouched.

## Non-goals

- Full Handler / Module metadata/runtime class extraction. Outlined as follow-up F5; lifecycle clarification and module orchestration are covered by L1–L2.
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

**The token is the seam.** Callback lists remain stable when `{token}` resolves to a runtime object. Factory and Invoker type contracts must still be updated. The names above describe the current code; L2 makes their responsibilities explicit.

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

**Shared logic** comes from `use Hook_Invoke_Methods;`: priority and tag resolution, and conditional checks. Relax that trait's `@phpstan-require-implements Can_Hook` so `Callback` can use it. The annotation adjustment is phpdoc-only, but the existing callable-priority defect must also be fixed before S2 relies on the trait (see review findings, bead `di-upr`).

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

**Unresolved compatibility requirement:** a reconstructed decorator alone is insufficient. `fired` and `firing` are documented on `Can_Invoke`, and the view's `target` would refer to its own `invoke()` rather than the registered callback. Preserving an `Action`/`Filter` type hint and resolved tag does not preserve live state or callable identity. Before S2, design and test delegation to the owning runtime, or explicitly approve a narrower compatibility contract. A CHANGELOG entry alone does not satisfy the current compatibility goal.

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
| `Factory::make( array $hook )` | If `is_plain_callback( $hook['type'] )`, return `new Callback( CallbackDefinition::from_data( $hook ), $this->ctr() )`. Otherwise use the legacy path unchanged. Return `Can_Hook\|Callback`, since this factory also constructs handlers/modules. |
| `Factory::save_hook()` | When started and the hook is a plain `Filter`/`Action`, store `new Callback( CallbackDefinition::from_data( $hook->get_data() ), $ctr )` under the token instead of the decorator. It still **returns the decorator**: Parser needs its metadata during discovery, while `register_methods()` reads its token. |
| `Factory::get_hook()`, `Invoker::add_callback()` | Accept/return the appropriate `Can_Invoke\|Callback` union. |
| `Factory::get()`, `Factory::get_callbacks()` | Preserve the broader handler/module return contract of `get()` while allowing `Callback`; update callback collection annotations. |
| `Factory::load_callbacks()`, `Factory::save_hook()` | Define how an existing runtime callback passes through this path without decorator mutators. `load_callbacks()` currently forwards every item into `save_hook(Can_Hook)`; annotation widening alone is insufficient. Preserve the registered callback object's identity. |
| `Parser`, `Compiler`, `Container`, `App_*` | No callback-extraction change. |
| `Invoker`, module orchestration | L2 clarifies lifecycle operations before S3 routes callbacks; preserve the strategy contract during routing. |
| Decorator runtime | Retained for subclasses during this transition; a compatibility view may require targeted delegation in S2. |

**Container compilation (`cache_app`).**
`Callback` objects are only ever created by the `Factory::make` factory at resolution time, or by `set()` at runtime. No objects end up inside definitions, so the compiled container is unaffected.

**Transitional duplication.**
`Filter` keeps its runtime methods because the subclasses still use them. For plain `Filter`/`Action` those methods become dead paths. They are deleted in F6 (B3.1).

## Compatibility checklist

Each callback item needs a test in S2 or S3. L1–L2 cover the module and handler lifecycle separately.

- [ ] Callback tokens are byte-identical, including tags with `{}` and modifiers.
- [ ] `has_filter()` / `has_action()` report the same priority, and the same accepted-args count is registered.
- [ ] Priority forms all resolve: int, constant name, `filter:default` string, callable array, and programmatically supplied closure.
- [ ] `remove_filter()` works with `[ $instance, 'method' ]` (standard) and `[ $container->get( $token ), 'invoke' ]` (proxied).
- [ ] `INV_ONCE`, `INV_LOOPED`, `INV_SAFELY`, `INV_PROXIED` semantics are unchanged.
- [ ] `INV_SAFELY` returns `$args[0]` and logs; without it, the exception is rethrown.
- [ ] For lazy and JIT handlers, `{token}_{strategy}_init` fires exactly as before, and the callback is proxied.
- [ ] Callback context and `conditional` gating are unchanged; handler initialization conditions retain their strategy-specific evaluation point.
- [ ] `xwp_di_hooks_loaded_{$classname}` still fires.
- [ ] `Invoker::get_actions()` output is unchanged.
- [ ] An existing `hook-definition.php` cache loads without regeneration.
- [ ] `!self.hook` receives an `Action`/`Filter` with a resolved `tag`, live documented state, and the registered callable target under the compatibility contract settled before S2; `!self.handler` receives the handler.
- [ ] A `Dynamic_Filter`, `Ajax_Action`, `REST_Route` or `CLI_Command` token still resolves to its decorator.
- [ ] The container still compiles with `cache_app` on.

## Risks

| Risk | Mitigation |
|---|---|
| Token drift orphans handler callback lists | S1 parity tests against `get_token()` on real decorators |
| `!self.hook` view loses live state or callable identity | Resolve delegation or an explicitly revised compatibility contract before S2 |
| Two runtimes exist during the transition | Exact-class routing; F6 removes legacy methods only after built-in and custom subclass compatibility is settled |
| `save_hook()` returns a different object than it stores | Preserve discovery callers' decorator metadata access and runtime token identity; cover both producer paths in S3 |
| Scanning or attachment constructs a JIT handler too early | L1 construction counters and ordered lifecycle traces, repeated through the S3 wiring |
| Module context hides definitions or suppresses unrelated imported-module initialization | L2 tests for unconditional services and independent imported-module scheduling |
| Repeated handler registration installs duplicate scheduling hooks | Explicit registration state and L1–L2 idempotency coverage |

## Review findings

- **Callable priorities already fail:** `Hook_Invoke_Methods::resolve_priority()` calls `defined($prio)` before checking arrays or closures. A local PHP probe reproduced `TypeError` for both. `call_priority()` also accepts only `array|string`, excluding closures. Bead `di-upr` tracks a focused fix and regression coverage; the runtime split must not silently inherit this defect.
- **Registry deduplication is incomplete:** `Invoker::add_handler()` returns early for an existing entry, but `register_handler()` continues its fluent chain and can install scheduling hooks again. L2 needs a registration guard that covers the whole operation.
- **A false initialization result does not stop the current chain:** `Invoker::init_handler()` returns the invoker even when `Handler::load()` returns false. Callers can continue discovery and attachment attempts. L1 must characterize the resulting behavior before helper extraction changes control flow; eligibility failures and exceptions are not interchangeable.
- **Factory type changes span the complete path:** general hook factories still construct modules/handlers, and runtime callbacks cannot be passed through decorator-only mutators. Test `get_callbacks()` and `load_callbacks()` as well as the main Invoker path.
- **Custom subclasses outlive the built-in ports:** exact-class routing protects them in S3. Porting F1–F5 alone does not prove that deleting inherited decorator runtime methods in F6 is compatible. Define the extension migration policy before removal.
- **Data conversion must stay inert:** S1 maps raw declarations. It must not evaluate a priority callable, resolve a modifier from the container, or run a condition while constructing `CallbackDefinition`.

## Testing

- **Unit** (`tests/Unit/Definition/CallbackDefinition_Test.php`):
  - `from_data()` maps every field.
  - Priority stays raw; conversion does not evaluate priorities, modifiers, or conditions.
  - Token parity for a plain tag, a tag with `{}`, and a tag with modifiers.
- **Integration** (`tests/Integration/`, needs WP):
  - L1 traces construction, initialization callbacks, attachment, and execution for every strategy, with cache on and off. Begin with JIT versus LAZY: JIT attaches without construction and evaluates its initialization condition on invocation; LAZY requests initialization during attachment.
  - Cover no-callback LAZY handlers, rejected initialization followed by another opportunity, successful initialization once, duplicate registration, and default current-action/current-priority-plus-one scheduling. Separate current-behavior characterization from regression tests for defects fixed in L2.
  - L2 verifies services/definitions remain available outside a module's handler context, module initialization finishes before handlers are triggered, and imported modules retain their own scheduling.
  - A `Callback_Test` that drives `Callback` directly (S2).
  - A wiring test that boots a fixture app with hook cache on and off and walks the checklist (S3).
- **Fixtures** go under `test/fixtures/shared/`:
  - a handler with plain `#[Filter]`/`#[Action]` covering each `INV_*` flag and `!self.hook`/`!self.handler` params;
  - LAZY and JIT handlers with construction and initialization counters, plus a condition whose result changes before invocation;
  - one `Dynamic_Filter` method as the legacy-path control.
- **Gates:** `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`, `vendor/bin/phpcs`, and `examples/simple-plugin` still bootstraps.

## Slice plan

Each implementation slice is one bead and one PR. Beads tracks execution status; these sections describe scope and acceptance criteria. Establish lifecycle behavior with L1, clarify orchestration with L2, then extract the callback runtime. S1 is independently safe metadata work and can proceed without changing runtime behavior. Module and handler runtime class extraction remains F5.

### L1 — Lifecycle contract and characterization

- **Scope:** capture the strategy matrix, discovery/initialization/attachment ordering, condition timing, and module lifecycle expectations. Settle the module and late-registration decisions listed above before implementing them.
- **Files:** this plan, `tests/Integration/`, fixtures under `test/fixtures/shared/`.
- **Acceptance:** existing strategy behavior is characterized with ordered events and construction counts. Desired module changes and known defects are distinguished from behavior already passing. JIT attachment does not construct a handler; LAZY attachment requests initialization; cached discovery preserves timing.

### L2 — Explicit orchestration and registration state

- **Scope:** clarify the internal operations beneath `register_handler()` while retaining its strategy branches; separate registration state from initialization and callback attachment; prevent duplicate scheduling; implement the agreed module lifecycle and context gate after the remaining decisions are resolved.
- **Files:** `src/Invoker.php`, targeted module/handler runtime code as required, integration tests and fixtures.
- **Acceptance:** L1 strategy behavior remains intact; module services/definitions remain unconditional; module initialization finishes before handler triggering; repeated registration installs scheduling hooks once; supplied instances and failed initialization attempts retain their intended lifecycle.
- **Depends on:** L1. Full runtime class extraction and callback token routing remain outside this slice.

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
  - Implement the settled `!self.hook` compatibility contract, including targeted decorator delegation if selected.
  - *Out:* wiring it into `Factory`.
- **Files:** `src/Hook/Callback.php`, `src/Traits/Hook_Invoke_Methods.php` (annotation adjustment after `di-upr`), any narrowly required decorator-view support, `tests/Integration/Callback_Test.php`, fixtures.
- **Acceptance:**
  - A manually constructed `Callback` passes every behavioral checklist item that does not involve `Factory` or `Invoker`.
  - phpstan and phpcs are clean.
- **Depends on:** S1, L2, callable-priority fix `di-upr`, and resolution of the `!self.hook` compatibility contract.

### S3 — Route plain `Filter` / `Action` tokens to `Callback`

- **Scope:**
  - `Factory::is_plain_callback()`.
  - `Factory::make()` and `Factory::save_hook()` routing.
  - Type widening in `Factory` and `Invoker`.
  - Safe handling of existing runtime callbacks through `get_callbacks()` / `load_callbacks()` without decorator-only mutation or callback identity changes.
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
  - Sync the module/handler lifecycle, the bounded dual-runtime transition, and any explicitly accepted compatibility changes with the migration docs and CHANGELOG.
- **Depends on:** S3.

### Follow-ups (designed later, not in this plan)

| ID | Work | Legacy methods to move into a `Callback` subclass | Depends on |
|---|---|---|---|
| F1 | Port `Dynamic_Filter` / `Dynamic_Action` | `load_hook`, `get_cb_args`, `invoke` (action) | S3 |
| F2 | Port `Ajax_Action` | `can_load`, `resolve_tag`, `load_hook`, `fire_hook`, `get_cb_args`, guard/nonce/cap checks | S3 |
| F3 | Port `REST_Route` | `invoke`, `get_callback`, `with_handler` | S3 |
| F4 | Port `CLI_Command` | `load_hook`, `get_callback`, `get_priority`, `run_cmd` | S3 |
| F5 | Handler/module definition and runtime extraction, including `HandlerDefinition::from_data()` and a runtime handler behind `Hook-{class}` | Preserve L1–L2 lifecycle and the handler contract from §2 | S3 |
| F6 | B3.1: strip runtime methods and `with_*()` from `Filter`/`Action` | Settle custom subclass migration and decorator-view dependencies before removal | F1–F5 and extension compatibility decision |

```text
L1 ─→ L2 ────────────┐
S1 ──────────────────┼─→ S2 ─→ S3 ─┬─→ S4
di-upr + view design ┘             ├─→ F1 ─┐
                                  ├─→ F2 ─┤
                                  ├─→ F3 ─┼─→ F6 (extension policy resolved)
                                  ├─→ F4 ─┤
                                  └─→ F5 ─┘
```
