# Migration 04 — Implementation Plan (Slice Breakdown)

> Original phase labels retained for reference. The callback split below supersedes the original Phase 3 ordering; beads records execution status.

## Reading this document

Each slice has:

- **Bead label** — `B0.1`, `B1.2`, etc. — used to refer to the issue.
- **Title** — what shows up in `bd list`.
- **Why** — the architectural reason this slice exists.
- **Scope** — what's in (and explicitly what's out).
- **Acceptance** — what proves the slice is done.
- **Depends on** — beads that must be `closed` before this slice starts.

The actual `bd create` commands and dependency wiring happen at the end of this file's companion plan execution. The labels here are stable references; bead IDs (`beads-XXX`) are assigned by `bd create`.

## Callback split update

The [definition split plan](definition-split-plan.md) is the implementation authority for lifecycle and runtime extraction. L1–L2 established lifecycle behavior; S1 added inert callback-data conversion; S2 added `Hook\Callback` and a typed forwarding view; S3 connected plain Filter/Action tokens in Factory and Invoker. S4 synchronizes these documents. The completed plain split did not require a Parser/Compiler cache-format change or prior removal of decorator mutators.

F1–F4 (Dynamic, AJAX, REST, CLI), F5 (handler/module extraction), and F6 (decorator cleanup after compatibility decisions) remain follow-up slices. Their designs are not implied by this documentation update. B3.2 remains broader than the completed plain-callback work.

## Phase 0 — Foundations

### B0.1 — Lock public API surface for 2.0

- **Why:** Until the surface is locked, every later slice has license to add to it. The lock comes first because it disciplines everything that follows.
- **Scope:** Audit `src/Functions/`, `src/Decorators/`, `src/Interfaces/`, `src/Container.php`. Mark everything else `@internal` in PHPDoc. Update [migration-03-public-api.md](migration-03-public-api.md) with any corrections discovered during the audit. *Out:* writing new public APIs.
- **Acceptance:** Every public class/function in `src/` is either listed in `migration-03-public-api.md` or has `@internal` in its PHPDoc. PHPStan custom rule (or grep) confirms no orphans.
- **Depends on:** —

### B0.2 — Set CI matrix for PHP 8.1–8.4, drop upper bound in composer

- **Why:** Current beta `composer.json` requires `>=8.1 <8.5`; removing that ceiling is still separate planned work. PHP 8.1 native is the floor. No upper bound — we don't want to time-bomb the install.
- **Scope:** `composer.json` PHP constraint becomes `>=8.1`. CI workflow runs jobs for PHP 8.1, 8.2, 8.3, 8.4. *Out:* tooling/lint setup beyond what CI needs.
- **Acceptance:** `composer install` works on PHP 8.1, 8.2, 8.3, 8.4. CI matrix passes for all four.
- **Depends on:** —

### B0.3 — Write migration plan docs (migration-00 through migration-05)

- **Why:** This very document set. Reference for all later slices.
- **Scope:** Six docs in `docs/migration-XX-*.md`. *Out:* anything that isn't the planning content itself.
- **Acceptance:** All six files committed to `beta`. Reviewed for accuracy.
- **Depends on:** —

## Phase 1 — Definition layer

### B1.1 — Introduce `src/Definition/` with `HookDefinition` interface + `ModuleDefinitionHelper`

- **Why:** This is the missing layer. Without it, decorators stay fused to the runtime.
- **Scope:** Port `HookDefinition` and `ModuleDefinitionHelper` from `rewrite` branch. Adapt to beta's class layout (no `old/` directory). Add to `src/Definition/Helper/`. Add `module()` factory function in `src/Functions/xwp-di-definition-fns.php`. *Out:* using the helpers from anywhere in the existing code yet — that's later slices.
- **Acceptance:** `new ModuleDefinitionHelper(MyModule::class)` produces a PHP-DI `ObjectDefinition`. Unit test confirms the metatype and constructor parameter wiring.
- **Depends on:** B0.1.

### B1.2 — Add `HandlerDefinition` and `CallbackDefinition` value objects

- **Why:** Cover the metadata that currently lives mutably on `Decorators\Handler` and `Decorators\Filter`. These describe inputs to runtime objects; CallbackDefinition now feeds the plain Callback runtime.
- **Scope:** Two immutable value objects. Constructor takes all data. Getters only — no setters, no with_*().  *Out:* using them in Parser yet.
- **Acceptance:** Unit tests for construction, equality, getter coverage.
- **Depends on:** B1.1.

### B1.3 — Add `ServiceDefinition` for autowired services and explicit service definitions

- **Why:** Modules declare `services[]`. Today they're plain class names. We want a typed definition that supports autowire vs factory vs value service definitions.
- **Scope:** `ServiceDefinition` value object + small builder. *Out:* PHP 8.5 closure-as-attribute factories — that's 3.0.
- **Acceptance:** Round-trip from `ServiceDefinition` to PHP-DI definition object verified.
- **Depends on:** B1.1.

### B1.4 — Add definition validation: cycle detection, missing dependencies, schema

- **Why:** Catch mis-wired modules at compile time, not at WP-init time. Frenzy detection at the framework level.
- **Scope:** Walks the definition graph. Detects: import cycles, undeclared dependencies, conflicting service IDs. Throws structured exceptions. *Out:* runtime validation — that's done.
- **Acceptance:** Unit tests cover each failure mode with a fixture that reproduces the bug.
- **Depends on:** B1.1, B1.2, B1.3.

## Phase 2 — Compiler refactor

### B2.1 — Refactor `Hook/Parser` to emit Definition objects, not decorator instances

- **Why:** Today `Parser` produces both raw arrays and decorator instances. After this slice, it produces only Definition objects.
- **Scope:** Rewrite `Parser::parse_module()` and friends to return `ModuleDefinition[]` / `HookDefinition[]` / `ServiceDefinition[]`. *Out:* changing what Parser does — input contract identical, output type changes.
- **Acceptance:** Existing parser tests (or new tests if absent) pass with definition-typed output. No `Filter` / `Action` / `Module` decorator instances created during parse.
- **Depends on:** B1.1, B1.2, B1.3.

### B2.2 — Refactor `Hook/Compiler` to serialize primitive arrays, not `var_export`'d objects

- **Why:** Cache file becomes stable across decorator constructor changes. Inspectable. Diffable.
- **Scope:** Rewrite `Compiler::compile()` to serialize Definition objects to plain arrays. Rewrite `Compiler::read()` (or equivalent) to consume the new format. Old format dies — no migration shim. *Out:* changing the cache *file path* or *invalidation strategy*.
- **Acceptance:** Round-trip test: parse → compile → read → equals original. Cache file is human-readable. Diff between two runs over identical input shows no spurious changes.
- **Depends on:** B2.1.

## Phase 3 — Definition and runtime split

### B3.1 — Remove obsolete decorator runtime and mutators after the ports

- **Why:** Attribute declarations should describe metadata independently of live execution state.
- **Scope:** F6 of the [split plan](definition-split-plan.md). Remove obsolete mutation/dispatch behavior only after specialized callback and handler/module ports. Preserve public attribute syntax. Settle custom-subclass migration and typed-view dependencies before removing inherited methods.
- **Acceptance:** Remaining decorator behavior is justified by the agreed compatibility policy; runtime ownership is separate and consumer migrations are documented. Current beta still contains `with_*()`, `invoke()`, `load()`, and `can_load()` paths.
- **Depends on:** F1–F5 and the extension/view compatibility decision. Parser changes alone do not make this cleanup safe.

### B3.2 — Extract per-callback runtime behind stable tokens

- **Why:** Each callback needs one execution owner and a stable WordPress callable identity. This replaces the central `Hook/Dispatcher` and generated-closure sketch.
- **Implemented:** S1–S3 provide `CallbackDefinition::from_data()`, `Hook\Callback`, the memoized typed `!self.hook` view, and exact Filter/Action routing in Factory and Invoker. Standard hooks retain handler-method callables; proxies register the Callback's `invoke`. Cached and runtime producers preserve tokens and metadata format.
- **Remaining scope:** F1–F4 port specialized callbacks; F5 extracts handler/module runtime while preserving the L1–L2 lifecycle contract. Invoker remains the coordinator.
- **Acceptance:** Each port has focused behavior tests plus cache and integration coverage. Custom subclasses continue working until their migration is handled. Removing decorator runtime is the final F6/B3.1 step, not a prerequisite for plain callback routing.
- **Dependencies:** Completed plain extraction used S1, L1–L2, callable-priority fixes, and the agreed forwarding-view contract. B2.1/B2.2 remain separate definition-graph/compiler work.

## Phase 4 — Module composition

### B4.1 — Module imports/handlers/services via `ModuleDefinitionHelper` composition

- **Why:** Today `imports[]` is an attribute argument array. We want it to be definition-driven so modules compose at the DI layer, not at the decorator layer.
- **Scope:** Modules can declare composition either via the `#[Module(imports: [...], handlers: [...], services: [...])]` attribute (existing) or via `ModuleDefinitionHelper` returned from a `configure()` static method. Both paths flow into the same definition graph. `handlers` and `services` enter via the helper. *Out:* removing the attribute-array path — both styles coexist. Module `exports` / encapsulation barriers are deferred to 3.0 (see [migration-00-context.md](migration-00-context.md)).
- **Acceptance:** A module declared via `ModuleDefinitionHelper` and one via attribute produce equivalent definition graphs.
- **Depends on:** B1.1, B1.4.

## Phase 5 — Verification

### B5.1 — Definition and runtime verification

- **Why:** Without tests we cannot lock anything; the lock contract assumes regression detection works.
- **Scope:** Definition unit tests and WordPress integration tests for bootstrap, attachment, invocation, initialization strategies, and caches. The current suites already cover the plain callback split and lifecycle; each specialized port extends them.
- **Acceptance:** `composer test` passes using the explicit unit/integration suites, with relevant static checks and example validation. The original >80% coverage target applies to `src/Definition/` and the extracted runtime classes, including `src/Hook/Callback.php`; it is a release target, not a claim that coverage has been measured or reached.
- **Depends on:** B3.2, B4.1.

## Phase 6 — Dogfood + ship

### B6.1 — Port one production plugin to v2.0 as canonical example

- **Why:** This is the truth detector. If a real plugin can't migrate, the API isn't done.
- **Scope:** Pick one of the 5 production plugins. Fork it (the v1.x version stays in production on master/1.x of this lib). Port the fork to consume v2.0. Land the port either in `examples/` or as its own repo, referenced from `examples/`. Document the diff: what changed at the consumer level, what stayed the same. *Out:* migrating all 5 plugins.
- **Acceptance:** The ported plugin runs in a WP environment, registers its hooks, and behaves identically to its v1.x counterpart. The diff document is checked in.
- **Depends on:** B5.1.

### B6.2 — Tag `2.0.0-beta.1`, collect feedback, tag `2.0.0` GA

- **Why:** The lock event. After this, public surface is frozen until 3.0.
- **Scope:** Tag `2.0.0-beta.1`. Announce. Soak window: 2 weeks minimum, longer if real usage shakes out issues. During the soak: only bug fixes, no new public API. After soak: tag `2.0.0`. *Out:* implementing 2.1 features.
- **Acceptance:** Tag pushed. CHANGELOG entry. Migration guide for v1.x → v2.0 published.
- **Depends on:** B6.1.

## Dependency ordering

The original B3.1 → B3.2 dependency is superseded for the callback split:

```text
L1–L2 + S1 + priority/view decisions → S2 → S3 → S4
                                           ├→ F1–F4 specialized callback ports ─┐
                                           └→ F5 handler/module extraction ────┼→ F6 / B3.1
                                                        compatibility policy ─┘
B1 definitions → B2.1 Parser → B2.2 Compiler (separate work)
Completed runtime ports + B4.1 composition → B5.1 → B6.1 → B6.2
```

The original B labels remain useful for the broader migration. Use the split-plan slices and beads for the actual execution sequence; the completed S1–S3 work does not mean all of B3.2 is finished.

## Slice sizing guideline

- **Small slice:** 1–3 days of focused work, one PR, one merge.
- **Medium slice:** up to 1 week, possibly with a sub-bead split if scope grows.
- **No large slices.** If a slice would take more than a week, split it into beads.

A slice that grows past its scope mid-work is the frenzy signal. File a follow-up bead, close the current scope, do not extend.

## How to start

```bash
bd ready                                 # see what's unblocked
bd update <bead-id> --claim              # claim the slice
# ... work ...
bd close <bead-id>                       # close when acceptance met
git add <task-files> && git commit        # preserve unrelated changes
# Follow AGENTS.md for rebase, beads sync, and branch push.
```

`B0.1`, `B0.2`, and `B0.3` have no dependencies. They are the entry points.
