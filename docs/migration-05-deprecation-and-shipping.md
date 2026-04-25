# Migration 05 — Deprecation, Shipping, and the v1.x Story

> When 2.0 ships, what happens to v1.x master, what 3.0 inherits, and the migration path for the 5 production plugins.

## The two-track plan

We maintain two release lines for a bounded period:

- **`master` / `1.x`** — current shipped runtime. Bug fixes only. The 5 production plugins live here.
- **`beta` → `2.x`** — the new clean DI library. Opt-in for new plugins; existing plugins migrate when ready.

This is **not** a "two architectures forever" trap because:

1. There is a fixed sunset for v1.x (see calendar below).
2. v1.x receives only bug fixes, never features.
3. The dogfood port (slice B6.1) proves a migration path exists for the 5 production plugins.

## Release calendar

Dates are intentions, not commitments. They communicate cadence; missing one is a planning signal, not a failure.

| Milestone | Estimated date | Gate |
|---|---|---|
| `2.0.0-alpha.0` (internal) | 2026-05-15 | Phase 0 + Phase 1 closed (B0.x + B1.x) |
| `2.0.0-alpha.1` | 2026-06-01 | Phase 2 closed (B2.1, B2.2) |
| `2.0.0-alpha.2` | 2026-06-15 | Phase 3 closed (B3.1, B3.2) |
| `2.0.0-beta.1` | 2026-07-01 | Phase 4 + Phase 5 closed (B4.1, B5.1). **Public API lock starts here.** |
| `2.0.0-rc.1` | 2026-07-15 | B6.1 closed; dogfood plugin in production |
| `2.0.0` GA | 2026-08-01 | 2-week soak window since rc.1 with no critical issues |
| `1.x` deprecation announce | 2026-08-01 | Same day as 2.0.0 |
| `1.x` security-only mode | 2027-02-01 | 6 months after GA |
| `1.x` end-of-life | 2027-08-01 | 12 months after GA |

If real usage during the alpha/beta cycle surfaces a fundamental issue, dates slip. The lock contract still holds — we don't relax it to ship sooner.

## v1.x → v2.0 migration path (for the 5 production plugins)

These are the steps a plugin author follows to migrate from `1.x` to `2.x`. Reference the dogfood port (B6.1) as the canonical worked example.

1. **Bump `composer.json`**: `"x-wp/di": "^2.0"`.
2. **Bump PHP requirement** if currently below 8.1.
3. **Bootstrap config**: same `xwp_load_app($config)` call. Most config keys carry over. Confirm `app_module` points to a `#[Module]`-decorated class.
4. **Module class**: still decorated with `#[Module(imports: [...], handlers: [...])]`. Optionally migrate to `ModuleDefinitionHelper` composition; not required.
5. **Handlers**: still `#[Handler(...)]`-decorated classes. No code changes for normal cases.
6. **Hooks**: `#[Filter]` / `#[Action]` / `#[REST_Route]` / etc. carry over. Constructor arguments are unchanged for users. (Internally they're now immutable — that's invisible.)
7. **Container access**: `xwp_app('app_id')` carries over.
8. **Removed APIs**: see "Breaking changes" below.

The expected migration cost for a typical plugin is one afternoon: bump composer, run tests, fix anything that hits a removed `@internal` API.

## Breaking changes from 1.x to 2.0

These are the things that *will* break unless the plugin code is updated. The list is intentionally short.

### Removed
- `Decorator->with_*()` fluent mutators (e.g. `$filter->with_handler($h)`). Decorators are immutable in 2.0. If consumer code touched these, it was already reaching into internals. Replacement: don't.
- `xwp_app(null)` accidental usage now throws. Pass the app ID explicitly.
- The legacy config-key compat shim in `App_Factory` is gone. Use the documented config keys.
- `Filter::invoke()` / `Action::invoke()` / etc. as runtime callables are no longer the WP callbacks. The Dispatcher is. Consumer code calling `$filter->invoke(...)` directly will fail. (No legitimate plugin should be doing this, but flag it.)

### Tightened
- PHP requirement: `>=8.1`.
- Public API surface: anything not in `migration-03-public-api.md` is now `@internal`. If a plugin reaches into `App_Factory` or `Invoker` directly, those reaches break in any 2.x minor.

### Renamed/moved
- (None planned. If a slice introduces a rename, it goes in `migration-03-public-api.md` as a public surface change and gets ratified before merge.)

## v1.x maintenance policy after 2.0 GA

- **2026-08-01 to 2027-02-01 (6 months):** v1.x receives bug fixes for security and severe regressions. New features go to 2.x.
- **2027-02-01 to 2027-08-01 (6 months):** v1.x is security-only. CVEs only.
- **2027-08-01:** v1.x EOL. No further updates. Plugins still on v1.x continue to function but receive no patches.

This timeline assumes 2.0 GA on 2026-08-01. Each calendar month of slip in GA shifts these dates by the same amount.

## What 3.0 inherits (the parking lot, formalized)

These items were deferred from 2.0. They stay deferred until 3.0 design begins. 3.0 design does NOT begin until 2.0 has been GA for at least 6 months — we want real usage feedback before opening the next door.

- PHP 8.5 closures-in-attributes for factory providers
- NestJS-runtime semantics: interceptors, guards, pipes, exception filters
- Custom event bus to replace WordPress hook semantics
- Async / lifecycle hooks beyond WP-native ones
- Custom compiled-container format beyond PHP-DI's
- `forRoot()` / `forFeature()` dynamic module patterns
- Integration of `option()` / `transient()` / `filtered()` / `wrapped()` definition helpers from master 1.x
- Anything proposed during 2.0 development that didn't fit a slice — file it as a 3.0-tagged bead

When 3.0 design begins, this list is the input. Anything not on it is starting fresh — and will face the same scrutiny that 2.0 went through.

## How a 2.x minor decision goes

A new public API arrives via a 2.x minor (e.g. 2.1.0) under these conditions:

1. Nontrivial demand from real usage in the field — not a hypothetical.
2. The addition is purely additive — no existing API changes.
3. The addition has a clear `@since 2.x` marker in PHPDoc.
4. The CHANGELOG entry includes the rationale (real-world signal that justified it).
5. Tests cover the new path.

A new public API does NOT arrive via:

- An "I had an idea" PR.
- A symmetrical-looking gap that nobody complained about.
- A NestJS feature being ported because it would be cool.
- A "while I'm in here" addition to an unrelated PR.

These get filed as 3.0 ideas instead.

## How an emergency response goes

If a critical bug surfaces post-GA:

1. Reproduce, write a regression test, fix, ship as 2.0.x patch.
2. If the bug is *also* present in v1.x and v1.x is still in maintenance, backport the fix.
3. CHANGELOG entry on both lines.

Force-pushes to a tag are forbidden. If a tag has issues, ship a higher patch number.

## Review of this document

This document is part of the lock surface in spirit. Changes to release dates are tracked normally; changes to *policy* (deprecation timeline, breaking-change criteria, lock semantics) require a PR with explicit reasoning and at least 24 hours of soak before merge.
