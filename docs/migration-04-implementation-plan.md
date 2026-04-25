# Migration 04 - Revised Implementation Plan

> The execution slices that take `beta` from pre-lock runtime branch to `2.0.0` GA.

Each slice maps to a Beads issue. Issue IDs in Beads use hyphenated forms such as
`di-bao-r0-1`; the section labels below keep the shorter `R0.1` notation for readability.

## Phase 0 - Truth and tooling

### R0.1 - Correct migration docs and public API inventory

- **Why:** The first draft had cross-document contradictions around the beta tag, API lock,
  namespaces, constants, and rewrite portability.
- **Scope:** Update `migration-00` through `migration-05` so they agree on lock point, release
  sequence, public API facts, and implementation order.
- **Acceptance:** Repo docs consistently describe `2.0.0-rc.1` as the freeze point, document helper
  functions as global where applicable, document `INIT_*` as strings, and avoid describing the
  `rewrite` branch as a complete Definition-layer source.
- **Depends on:** none.

### R0.2 - Add/verify test harness before runtime rewrites

- **Why:** Parser, compiler, dispatcher, and decorator changes need regression tests before code
  moves.
- **Scope:** Add or repair PHPUnit config, composer scripts, bootstrap files, and fixture loading
  needed for unit/runtime tests on `beta`.
- **Acceptance:** `composer test` or the documented fallback PHPUnit command runs and exercises at
  least one fixture app bootstrap test.
- **Depends on:** R0.1.

### R0.3 - Public/internal annotation pass

- **Why:** Current interfaces expose internals. Before implementation, code docs must identify what
  is stable and what is migration scaffolding.
- **Scope:** Annotate internal classes/methods and update docblocks for global functions,
  decorators, interfaces, and container methods.
- **Acceptance:** Public inventory in `migration-03` matches source docblocks and no orphan public
  class remains without a decision.
- **Depends on:** R0.1.

## Phase 1 - Definition schema

### R1.1 - Write executable definition/cache schema tests

- **Why:** The schema must be locked by tests before new parser/compiler work.
- **Scope:** Add tests that describe `ModuleDefinition`, `HandlerDefinition`, `CallbackDefinition`,
  `ServiceDefinition`, and primitive cache arrays.
- **Acceptance:** Tests fail only because the definition classes/serializers do not exist yet.
- **Depends on:** R0.2.

### R1.2 - Add immutable definition objects

- **Why:** Definitions replace mutable decorators as parser output.
- **Scope:** Add definition classes with constructor-only state, getters, and `to_array()` /
  `from_array()` or equivalent serializer methods.
- **Acceptance:** R1.1 tests pass. Objects serialize to primitive arrays only.
- **Depends on:** R1.1.

### R1.3 - Add `ModuleDefinitionHelper` and `module()`

- **Why:** Modules should be composable through PHP-DI definitions without relying only on attribute
  arrays.
- **Scope:** Implement the helper from the revised schema. Reuse ideas from `rewrite`, but do not
  treat it as a complete port.
- **Acceptance:** Unit tests prove `module(MyModule::class)` creates the expected PHP-DI
  definition/helper metadata.
- **Depends on:** R1.2.

## Phase 2 - Parser and compiler

### R2.1 - Parser emits definitions behind an adapter

- **Why:** Runtime behavior should remain stable while parser output changes.
- **Scope:** Refactor parser internals so module/handler/callback discovery creates definition
  objects. Keep an adapter that can still feed existing container definitions until dispatcher work
  lands.
- **Acceptance:** Existing fixture bootstrap still works. Parser tests assert no live decorator
  object is returned as public parser output.
- **Depends on:** R1.2.

### R2.2 - Compiler writes primitive deterministic cache

- **Why:** Hook cache must not depend on decorator constructors or object export.
- **Scope:** Replace object/cache export with primitive arrays matching `migration-01`.
- **Acceptance:** Parse -> compile -> read round trip passes. Two runs over identical input produce
  byte-identical cache files.
- **Depends on:** R2.1.

## Phase 3 - Runtime contract split

### R3.1 - Split metadata contracts from runtime execution contracts

- **Why:** Immutable decorators cannot implement interfaces that require mutation and loading.
- **Scope:** Introduce or revise internal runtime interfaces for loading/execution. Keep public
  metadata-facing contracts small. Update classes gradually without breaking bootstrap.
- **Acceptance:** `Can_Hook`, `Can_Invoke`, and `Can_Handle` no longer need to be frozen with
  `with_*()` methods as public metadata contracts.
- **Depends on:** R2.1.

### R3.2 - Dispatcher binds callbacks from definitions

- **Why:** WordPress should see dispatcher-owned callbacks, not decorator instances.
- **Scope:** Add `Hook\Dispatcher` with bind logic for action/filter callbacks from primitive
  definitions. Wire it through `Invoker` or container bootstrap without changing user syntax.
- **Acceptance:** Fixture action/filter callbacks register through dispatcher and fire.
- **Depends on:** R2.2, R3.1.

### R3.3 - Dispatcher owns invocation behavior

- **Why:** Runtime state belongs in dispatch, not metadata.
- **Scope:** Move context checks, conditionals, lazy/JIT initialization, safe/once/looped flags, and
  proxied invocation behavior into dispatcher/runtime collaborators.
- **Acceptance:** Focused tests cover standard, proxied, safely, once, looped, lazy, and JIT paths.
- **Depends on:** R3.2.

### R3.4 - Clean decorators after dispatcher migration

- **Why:** Mutators can only be removed after no active runtime path depends on them.
- **Scope:** Remove or internalize `with_*()`, `load()`, `can_load()`, container references, handler
  target references, and `invoke()` from decorators.
- **Acceptance:** Grep confirms public decorator APIs are constructor/getter metadata only, and all
  dispatcher tests still pass.
- **Depends on:** R3.3.

## Phase 4 - Module composition and validation

### R4.1 - Module helper composition

- **Why:** Attribute imports and PHP-DI helper composition should feed the same graph.
- **Scope:** Support modules declared via `#[Module(imports: ...)]` and via `module(...)->imports()`
  / `provides()` / `exports()` where implemented.
- **Acceptance:** Attribute module and helper module fixtures produce equivalent definition graphs.
- **Depends on:** R1.3, R2.1.

### R4.2 - Definition validation

- **Why:** Module graph problems should fail at compile time.
- **Scope:** Detect import cycles, missing module/handler classes, conflicting service IDs, and
  invalid callback references.
- **Acceptance:** Unit tests cover each failure mode with clear exception messages.
- **Depends on:** R4.1.

## Phase 5 - Dogfood, freeze, ship

### R5.1 - Port one production plugin

- **Why:** The dogfood port is the API truth detector.
- **Scope:** Port one of the production plugins to the revised v2 branch. Document exact consumer
  changes.
- **Acceptance:** Plugin runs in a WP environment and behaves like its v1 counterpart. Migration
  guide is updated with real findings.
- **Depends on:** R3.4, R4.2.

### R5.2 - Freeze at `2.0.0-rc.1`, soak, then GA

- **Why:** The public surface should freeze after implementation and dogfood, not before.
- **Scope:** Tag `2.0.0-rc.1`, run soak window, fix bugs only, then tag `2.0.0`.
- **Acceptance:** RC tag pushed, changelog/migration guide published, no critical issues during
  soak.
- **Depends on:** R5.1.

## Dependency graph

```
R0.1 -> R0.2 -> R1.1 -> R1.2 -> R2.1 -> R2.2 -> R3.2 -> R3.3 -> R3.4 -> R5.1 -> R5.2
  |       |        |       |       |       |       ^
  |       |        |       |       |       +-- R3.1
  |       |        |       +-- R1.3 -> R4.1 -> R4.2 --+
  |       +-- R0.3 ------------------------------------+
```

## Start command

```bash
bd ready
bd update <issue-id> --claim
```

Do not start runtime slices before R0.2 has a passing test command.
