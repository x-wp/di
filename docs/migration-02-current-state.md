# Migration 02 - Current State and Gap

> What `beta` currently contains, what is usable, and what must be corrected before runtime work.

## Snapshot

`beta` is an alpha-style runtime branch with custom `Container`, `Compiled_Container`, parser,
compiler, and hook factory code. It is not the `rewrite` branch, and it does not contain a complete
Definition layer.

Important current facts:

- `composer.json` still has `"php": ">=8.1 <8.5"`.
- The historical `v2.0.0-beta.1` tag already exists.
- `src/Functions/xwp-di-container-fns.php` and `src/Functions/xwp-di-helper-fns.php` define global
  functions, not namespaced `XWP\DI\...` functions.
- `src/Definition/` is absent on `beta`.
- `test/fixtures/` exists, but there is no complete PHPUnit test harness or `composer test` script
  on `beta`.
- `Hook\Parser` and `Hook\Factory` currently build and mutate decorator instances.
- `Hook\Compiler` writes `var_export()` output from parser data.

## Keep unless a slice proves otherwise

- `App_Factory` as the singleton app/container factory.
- `App_Builder` as the PHP-DI builder wrapper.
- `Container` and `Compiled_Container` as the app runtime boundary.
- Existing bootstrap/helper function names.
- Existing WordPress hook semantics and timing.
- Existing attribute names and common constructor arguments.

## Major refactor areas

### Parser and Factory

`Hook\Parser` currently stores mixed arrays, service IDs, aliases, values, extensions, and hook
tokens. It asks `Hook\Factory` to resolve module/handler/callback decorators, then records data from
those mutable objects.

This means parser migration is not just an output type change. It must replace decorator mutation
with definition creation while preserving module imports, extension imports, services, static
`configure()` / `define()` / `extend()` data, and handler callback discovery.

### Compiler

`Hook\Compiler` currently writes a cache file using `var_export()` of parser data. It must move to a
primitive schema that can be read without reconstructing decorator objects.

### Interfaces and decorators

The current `Can_Hook`, `Can_Invoke`, and `Can_Handle` interfaces require fluent mutators and
runtime methods such as `with_*()`, `load()`, and `can_load()`. Therefore they cannot stay as the
stable public metadata contracts while decorators become immutable.

The plan must split metadata contracts from internal runtime/execution contracts before mutators
are removed.

## Rewrite branch reality

`rewrite` is useful as design inspiration only. Its `ModuleDefinitionHelper` is a small stub and
several `src/Definition/*` files are empty. Do not describe Phase 1 as "port the Definition layer
from rewrite." It is new design work with a few reusable names.

## Immediate blockers before runtime changes

1. Docs must agree on release names, lock point, helper namespace, constants, and public/internal
   contracts.
2. Beads issues must match the revised slice order.
3. Test infrastructure must exist before parser/compiler/dispatcher rewrites.
4. The definition and cache schemas must be written down before implementation.

## Verification baseline

Before a runtime slice merges:

- Focused unit tests must cover the new behavior.
- Fixture app bootstrapping must be exercised by PHPUnit or a documented integration command.
- Hook cache output must be deterministic for identical input.
- A handler with `#[Action]`, `#[Filter]`, and at least one REST/AJAX/CLI-specific path relevant to
  the slice must register and invoke correctly.
