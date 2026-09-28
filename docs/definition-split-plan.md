# Definition Split Plan — Lifecycle and Callbacks

> Establish the module and handler lifecycle, then split `#[Filter]` / `#[Action]` metadata from runtime behavior. Callback container tokens resolve to a runtime `Hook\Callback` built from a `CallbackDefinition`, instead of to the decorator instance. Preserve strategy-specific ordering throughout the split.

## Review scope

This plan targets the `beta` architecture present in this checkout, including `Hook\Parser`, `Hook\Factory`, and the definition value objects. Repository notes describing the lightweight `master` runtime do not describe these producer and consumer paths.

The lifecycle conclusions below extend the original callback-only plan. The module context, initialization-condition, and late-registration policies were settled on 2026-09-28. They preserve the existing runtime contract; callback runtime extraction remains separate work.

`Hook\Callback` and its typed forwarding view are implemented (S2, `di-q15`). Factory and Invoker now route exact `Filter` and `Action` types through that runtime (S3, `di-70n`); specialized and custom subclasses keep their decorator runtime. `Callback_Runtime_Test` covers the runtime directly, while `Callback_Wiring_Test` and `Self_Hook_Test` cover routing and view identity with cold/warm caches and compiled containers.

## Relation to the migration docs

- Pulls [migration-04](migration-04-implementation-plan.md) **B3.2** forward for plain `Filter` / `Action`. It does not wait on B2.1 (Parser output), B2.2 (Compiler format) or B3.1 (strip `with_*()`).
- Replaces the central `Hook\Dispatcher` sketch in [migration-01](migration-01-target-architecture.md) §Layer 3 with **one runtime object per callback**, for three reasons:
  - The WordPress callable stays stable, so `remove_filter()` keeps working. Dispatcher closures would break it.
  - Per-callback state (`fired`, `firing`, `loaded`) has an owner.
  - The object *is* the container entry for the callback token. Nothing new has to be looked up.
- Migration-01 and migration-04 now describe this per-callback runtime and its staged extraction sequence (S4).
- Lifecycle slices L1–L2 precede the callback runtime work. They establish the strategy contract and clarify the existing orchestration before behavior moves out of decorators.
- Migration-00 permits this bounded transition: specialized/custom decorator runtime remains until the F1–F5 ports and F6 compatibility requirements are met.

## Agreed lifecycle

### Modules: unconditional definitions, gated and scheduled execution

Every module contributes its services and static `configure()` definitions to the application regardless of request context or `can_initialize()` outcome. Imports are discovered as part of the complete module graph. Including those declarations does not require constructing module instances. Definitions returned by `configure_async()` belong to runtime initialization and are not unconditional.

For the default `AUTO` strategy, the module's hook and priority determine when its initialization runs and when it triggers its handlers. The sequence below describes that default; explicitly selected strategies retain the timing described in the strategy contract below. In this discussion, `module_init` names that lifecycle step; the existing implementation uses `on_initialize()`. This plan does not add a new public initialization method.

```text
Build application
  └─ Collect module services, definitions, and imports

Register module through its parent
  └─ Check module context; excluded modules stop here
      └─ Schedule initialization using the module hook + priority

Module initialization point
  └─ Check context and can_initialize(); rejection stops runtime progress
      └─ Construct module, configure_async(), finish on_initialize()
          └─ Register eligible handlers and imported modules
              └─ Attach the module's own callbacks
```

Module context is a strict runtime gate. An excluded module does not enter lifecycle construction, `configure_async()`, or `on_initialize()`, and does not attach its own callbacks or register handlers/imports. Broader or explicit child contexts cannot override an excluded ancestor. Module services and static definitions remain available in the container. This preserves the existing context gate rather than introducing unconditional module initialization.

Handler context and initialization conditions remain additional checks. A module triggering a handler means registering it with the lifecycle coordinator; construction and callback attachment depend on its strategy.

Context restrictions cascade through imported modules as well as handlers and attributed callback methods on the module itself. Imported modules retain their own initialization hooks and priorities, but their parent must initialize successfully before registering them. Their own context and initialization conditions remain additional gates. This supersedes the earlier proposal for imports to have independent context eligibility.

`can_initialize()` is an additional runtime gate, evaluated at the module's initialization point. A false result prevents construction, asynchronous configuration, initialization callbacks, and descendant registration through that module. For `AUTO`, it also prevents callback attachment; `JIT` can attach proxies before attempting initialization. A normal rejection does not count as successful initialization; an `AUTO` module can retry when its scheduled hook occurs again. Successful initialization is retained without rerunning its initialization condition or callback.

The cascade describes registration through the module tree. The container remains flat: resolving a service is not module activation, and this work does not introduce ownership checks into direct container resolution or explicit handler registration.

### Discovery, registration, initialization, attachment, invocation

| Operation | Meaning | Current implementation |
|---|---|---|
| Discover | Read declarations and obtain handler/callback metadata; cacheable | `Parser`, `Factory::resolve_callbacks()`, `Invoker::register_methods()` |
| Register handler | Admit a handler into the app runtime once and arrange its strategy | `Invoker::register_handler()` and `add_handler()` |
| Initialize handler | Check initialization conditions, resolve its instance, configure it, and run `on_initialize()` | `Invoker::init_handler()` and `Handler::load()` |
| Attach callbacks | Register callables with WordPress | `Invoker::invoke_methods()`, `Callback::load()`; legacy subclasses use `Filter::load()` |
| Invoke callback | Execute the handler method when its hook fires | Direct method callable, `Callback::invoke()`, or legacy subclass runtime |

`register_handler()` is the strategy coordinator. Its existing `match` already expresses different lifecycle paths and should remain explicit. Registration can immediately cause initialization for `EARLY` or `NOW`; it does not imply that every strategy creates an instance.

Names currently obscure this separation: `register_methods()` discovers callbacks, `invoke_methods()` attaches them, and `load()` means initialization on a handler but attachment on a callback. `Invoker::load_handler($instance)` adopts an existing instance and enters it into registration.

The existing `init_handler()`, `register_methods()`, `invoke_methods()`, and scheduling helpers implement these boundaries beneath `register_handler()`. Registration state is separate from loaded handler state; `init_eager_handler()` and `attach_pending_methods()` handle rejected `EARLY`/`NOW` initialization without duplicate scheduling. No lifecycle-method renaming or new public API is required for L1–L2.

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

### Ordering and late registration

Application bootstrap, module activation, handler scheduling, and callback execution each have their own hook and priority. Preserve those stages. Handlers without an explicit hook schedule on the active action at its current priority plus one, including nested actions. Cache construction must not capture that request's hook or priority.

Callers own registration timing. Registering after a configured action has completed, or after its configured priority has passed during the current action, installs the listener for the next occurrence. There is no automatic catch-up or missed-hook warning. If the action never fires again, that scheduled work does not run in the request. Imported modules follow the same rule: a parent can register an import too late for its configured hook.

`NOW` still initializes and attaches immediately by strategy. `EARLY` initializes during registration but schedules attachment. Its explicit retry behavior is narrower than general catch-up: if its already-installed attachment listener ran while initialization was rejected, a successful retry finishes that pending attachment immediately. Newly registered `EARLY` handlers do not catch up a missed attachment hook.

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
[Factory::make()](../src/Hook/Factory.php) converts plain Filter/Action arrays with `CallbackDefinition::from_data()` and creates a `Callback`. Other types retain decorator reconstruction: `new $hook['type']( ...$hook['args'] )`, then `with_data( $hook['params'] )`.

A Factory without a container can still reconstruct decorator metadata; runtime conversion requires a container. This preserves the metadata-only construction path.

**Producer B: runtime (hook cache off, or user-registered instances).**
`Invoker::register_methods()` → `Factory::resolve_callbacks()` → `resolve_method_callbacks()` → `save_hook()`. Plain callbacks are converted through `make()` and stored under their token; other decorators are stored directly. Discovery still returns decorator metadata. Once the app has started, `get_callbacks()` returns the stored objects, including when it first discovers an uncached handler's methods. Existing token entries are retained when callbacks are loaded again.

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

#### Characterized behavior before extraction

Before extraction, `Self_Hook_Test` established the following behavior with cold/warm hook caches and compiled containers:

- The injected object is the exact `Filter` or `Action` stored under its callback token. Repeated attributes on one handler method have separate objects, tokens, and counters.
- `tag` is resolved at runtime, including modifiers; `method` identifies the handler method. The container, handler metadata, priority, accepted argument count, and attachment hook remain accessible through the decorator.
- During invocation, `firing` is true and `fired` contains the number of prior completed attempts. After return, `firing` is false and `fired` increases. An exception also increments `fired` in `finally`; an invocation skipped by `INV_ONCE` does not. Retaining the injected object preserves access to its changing state.
- For a proxied callback, `target` is the actual registered callable. Removal currently works with `$hook->target`, `array( $hook, 'invoke' )`, and `array( $container->get( $hook->get_token() ), 'invoke' )` because all three refer to the same object.
- Removing the WordPress listener does not reset `loaded`. Calling `load()` again returns true without reattaching it. Direct `invoke()` remains callable after removal and updates the same live state.

S3 updated the same-object and explicit view-object removal assertions to the accepted boundary below. The other state, invocation, and removal guarantees remain covered.

#### Selected direction and proposed delegation

On 2026-09-29 the user selected a typed forwarding view. The view remains a `Filter` or `Action`, while the callback token resolves to the separate `Callback` runtime. Consequently, `$hook === $container->get( $hook->get_token() )` is no longer promised after the split. A reconstructed decorator with copied counters is insufficient.

The proposed implementation keeps one runtime owner and one memoized view per callback:

| View surface | Delegation contract |
|---|---|
| `fired`, `firing` | Read the owner's current state on every access; never copy invocation counters into the view. |
| `target` | Return the owner's registered callable, including the owner's `invoke` identity for proxies. |
| `tag`, `method`, `get_tag()`, `get_priority()`, `get_num_args()` | Expose the owner's effective metadata and runtime resolution. |
| `get_handler()`, `get_container()`, `is_loaded()`, `get_init_hook()` | Forward to the owner so lifecycle and container identity stay consistent. |
| `load()`, `can_load()`, `invoke()` | Forward to the owner. Only the owner attaches, executes, and updates state. Action invocation still returns null. |
| `get_classname()`, `get_token()` | Preserve the definition's handler and byte-identical token. `Hook::get_token()` is final; construct the view from the original token inputs rather than overriding it. |

Add an internal owner link only to the runtime-backed plain decorator view. Legacy decorators without an owner continue their existing path, including specialized subclasses. Runtime execution must not call back through the view's forwarding operations; injection returns the memoized view, and state always belongs to the runtime. Build/cache output contains definition data only; owner links and views are created per runtime and never serialized.

The two removal forms that name the runtime remain available: `$hook->target` and `array( $container->get( $hook->get_token() ), 'invoke' )`. A direct `$hook->invoke()` call forwards normally, but this does not give `array( $hook, 'invoke' )` the owner's WordPress callable identity.

**Accepted compatibility boundary (2026-09-29):** the user confirmed `!self.hook` has not been used in production and authorized the forwarding-view approach, including target-based removal. `array( $hook, 'invoke' )` is not a supported WordPress removal identity for the separate view; use `$hook->target` or the container runtime's callable. Direct view invocation still forwards. `Callback_Runtime_Test` verifies this boundary directly; `Self_Hook_Test` verifies it through Factory routing. These identity changes are recorded in [the migration notes](migration-05-deprecation-and-shipping.md#current-beta-callback-split).

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
`Filter` keeps its runtime methods because subclasses still use them, and its typed compatibility view forwards calls to Callback. Removing legacy execution code and any remaining mutators is F6 (B3.1), after the view and custom-subclass dependencies are settled.

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
| Context or initialization conditions remove definitions, or descendants bypass their ancestor's gate | L2 tests for unconditional services/static definitions, strict context cascades, and imported-module scheduling after parent initialization |
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
  - L2 verifies services/static definitions remain available when module context or initialization conditions reject runtime work; context gates module-owned callbacks, handlers, and nested imports; initialization finishes before child registration; imported modules retain their own scheduling; late registration waits for the next hook occurrence.
  - `Default_Schedule_Test` covers nested-action defaults with cold/warm hook caches and compiled containers. `Module_Lifecycle_Test` covers the module contract and context changes across requests sharing cached definitions. `Handler_Context_Test`, `Handler_Lifecycle_Test`, and `Handler_Retry_Test` cover registration, strategy timing, supplied instances, and rejected initialization.
  - `Callback_Runtime_Test` drives `Callback` directly (S2): standard and proxied identity, live typed views and removal, context/condition retries, LAZY/JIT timing, once/loop/safe invocation, parameter tokens, priorities, argument counts, and container dependency injection.
  - A wiring test that boots a fixture app with hook cache on and off and walks the checklist (S3).
- **Fixtures** go under `test/fixtures/shared/`:
  - a handler with plain `#[Filter]`/`#[Action]` covering each `INV_*` flag and `!self.hook`/`!self.handler` params;
  - LAZY and JIT handlers with construction and initialization counters, plus a condition whose result changes before invocation;
  - one `Dynamic_Filter` method as the legacy-path control.
- **Gates:** `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`, `vendor/bin/phpcs`, and `examples/simple-plugin` still bootstraps.

## Slice plan

Each implementation slice is one bead and one PR. Beads tracks execution status; these sections describe scope and acceptance criteria. Establish lifecycle behavior with L1, clarify orchestration with L2, then extract the callback runtime. S1 is independently safe metadata work and can proceed without changing runtime behavior. Module and handler runtime class extraction remains F5.

### L1 — Lifecycle contract and characterization

- **Scope:** capture the strategy matrix, discovery/initialization/attachment ordering, condition timing, and the settled module and late-registration policies above.
- **Files:** this plan, `tests/Integration/`, fixtures under `test/fixtures/shared/`.
- **Acceptance:** existing strategy behavior is characterized with ordered events and construction counts. Known defects are distinguished from behavior already passing. JIT attachment does not construct a handler; LAZY attachment requests initialization; cached discovery preserves timing; module gates preserve definitions and prevent excluded descendant activation.

### L2 — Explicit orchestration and registration state

- **Scope:** clarify the internal operations beneath `register_handler()` while retaining its strategy branches; separate registration state from initialization and callback attachment; prevent duplicate scheduling; preserve the agreed module lifecycle and context cascade, fixing only demonstrated departures from that contract.
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
  - Implement the selected typed forwarding view and owner delegation in Design §3.
  - *Out:* wiring it into `Factory`.
- **Files:** `src/Hook/Callback.php`, `src/Traits/Hook_Invoke_Methods.php` (annotation adjustment after `di-upr`), narrowly required decorator-view support in `src/Decorators/Filter.php`, `tests/Integration/Callback_Runtime_Test.php`.
- **Acceptance:**
  - A manually constructed `Callback` passes every behavioral checklist item that does not involve `Factory` or `Invoker`.
  - The memoized view preserves Action/Filter type hints and live state, delegates runtime operations without a second state machine, and exposes the owner's removable callable through `target`. Standalone tests verify the accepted identity boundary in Design §3; the legacy characterization assertions change with Factory routing in S3.
  - phpstan and phpcs are clean.
- **Depends on:** S1, L2, callable-priority fix `di-upr`, and the settled `!self.hook` contract characterized in `di-965`.

### S3 — Route plain `Filter` / `Action` tokens to `Callback`

- **Scope:**
  - `Factory::is_plain_callback()`.
  - `Factory::make()` and `Factory::save_hook()` routing.
  - Type widening in `Factory` and `Invoker`.
  - Safe handling of existing runtime callbacks through `get_callbacks()` / `load_callbacks()` without decorator-only mutation or callback identity changes.
- **Files:** `src/Hook/Factory.php`, `src/Invoker.php`, `src/Traits/Hook_Token_Methods.php`, callback collection annotations in `src/Functions/xwp-di-helper-fns.php`, `tests/Integration/*` and fixtures. The example's `WC_Module` uses the required `hook:` constructor argument.
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
