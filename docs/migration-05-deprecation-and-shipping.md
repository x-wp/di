# Migration 05 - Deprecation and Shipping

> How v1.x, beta, RC, and GA should move from here.

## Release lines

- **`master` / `1.x`**: current shipped runtime. Bug fixes and severe regressions only once v2 is
  usable.
- **`beta` / `2.x pre-release`**: migration branch for the Definition -> Compilation -> Dispatch
  architecture.

The two-line period is bounded by the v1 sunset after v2 GA. It is acceptable while the migration
path is being proven, but v1 should not receive new feature work once v2 reaches RC.

## Existing tag history

These tags already exist and must not be retconned:

- `v2.0.0-alpha.1` through `v2.0.0-alpha.9`
- `v2.0.0-beta.1`

The next mutable pre-release should be `2.0.0-beta.2` or later. The real API freeze is
`2.0.0-rc.1`.

## Revised release calendar

Dates are planning targets. If implementation or dogfood exposes real issues, dates slip.

| Milestone | Estimated date | Gate |
|---|---|---|
| `2.0.0-beta.2` | 2026-05-15 | Revised docs, Beads, public inventory, and test harness complete. |
| `2.0.0-beta.3` | 2026-06-15 | Definition layer, parser adapter, and primitive compiler complete. |
| `2.0.0-beta.4` | 2026-07-15 | Dispatcher and decorator cleanup complete. |
| `2.0.0-rc.1` | 2026-08-01 | Dogfood plugin passes. Public API freezes here. |
| `2.0.0` GA | 2026-08-15 or later | Minimum two-week RC soak with no critical issues. |
| `1.x` deprecation announce | same day as GA | v1 bug-fix-only window starts. |
| `1.x` security-only mode | six months after GA | Security fixes only. |
| `1.x` end-of-life | twelve months after GA | No further v1 patches. |

## Migration expectations

Normal attribute users should see a small but real migration:

1. Bump Composer to `x-wp/di:^2.0`.
2. Raise PHP requirement to `>=8.1`.
3. Prefer documented `app_*` config keys.
4. Keep `#[Module]`, `#[Handler]`, `#[Action]`, `#[Filter]`, REST, AJAX, CLI, and `Infuse`
   attributes with documented constructor arguments.
5. Stop using decorator `with_*()`, `load()`, `can_load()`, or `invoke()` methods directly.
6. Stop reaching into `App_Factory`, `Invoker`, parser/compiler, or decorator base classes from
   plugin code.

The dogfood port must replace guesses in this section with observed changes.

## Breaking-change candidates before RC

These are allowed before RC if documented in the migration guide:

- Removing the legacy config-key shim.
- Removing or internalizing fluent decorator mutators.
- Splitting current runtime-heavy interfaces into public metadata contracts and internal runtime
  contracts.
- Removing direct callable usage of decorator `invoke()` methods.
- Dropping deprecated misspelled aliases such as `INIT_DYNAMICALY` and `INIT_DEFFERED`, if dogfood
  proves they are not needed.

After RC, these require a new major unless explicitly marked experimental/internal before RC.

## v1.x maintenance

After 2.0 GA:

- First six months: v1 receives security and severe-regression fixes.
- Next six months: v1 receives security fixes only.
- After twelve months: v1 is EOL.

Each slip in 2.0 GA shifts the v1 sunset by the same amount.

## 2.x minor policy

2.x minors may add public API only when real usage demands it. Additions need tests, PHPDoc
`@since`, changelog entries, and migration-doc updates. Hypothetical symmetry with NestJS or other
frameworks is not enough.

## Emergency response

For critical bugs after GA:

1. Reproduce with a regression test.
2. Fix on 2.x and release a patch.
3. Backport to v1 only if v1 is still in maintenance and the bug exists there.
4. Never force-push tags.
