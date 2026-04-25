# Migration 00 — Context

> Why v2.0 exists, what it's trying to be, and what discipline we're holding ourselves to.

## The problem with v1.x (master)

The library works in production. Five plugins ship on it today. But its biggest architectural flaw is structural: the attribute classes (`Filter`, `Action`, `Handler`, `Module`) are simultaneously declarative metadata, mutable state containers, and live WordPress callbacks. One class does three jobs. Every painful symptom — reflection-per-request, `xwp_app($container_id)` global lookups, the singleton `Invoker`, the static `$hooks` cache, the `with_*()` fluent mutation API — is downstream of that fusion.

The fix is structural, not cosmetic. You cannot solve "reflection cost" with caching while the cached objects still hold references to live containers and handlers. You cannot make hooks unit-testable while the only way to invoke one is to instantiate `App_Factory`. The split has to happen in the source, not in a wrapper.

## What v2.0 is

Three layers, three responsibilities, no fusion:

1. **Definition** — pure value objects that describe modules, hooks, handlers, services. No reflection, no WordPress, no runtime state.
2. **Compilation** — a build pass that walks the module tree, reflects once, and produces a definition graph. Output is serializable plain data.
3. **Dispatch** — a runtime that consumes the compiled graph, registers WordPress hooks, and handles invocation. Zero reflection at request time.

Public surface stays familiar: decorators on classes, `xwp_load_app()` to bootstrap, `xwp_app()` to fetch the container. What changes is what happens behind those calls.

## What v2.0 is *not*

- Not a port of NestJS's runtime. We're taking the *ergonomics* (declarative modules, attribute-driven wiring, definition helpers) and grafting them onto WordPress's existing execution model. We are not building a parallel pipeline of guards/interceptors/pipes that competes with WP hooks.
- Not backwards-compatible with v1.x master. v1.x plugins stay on v1.x; v2.0 is opt-in. No `old/` adapter, no v1.x decorator shims.
- Not a place to land PHP 8.5 features. PHP 8.1 floor, no upper bound. Closures-in-attributes, factory providers as attribute arguments, and similar 8.5-only features are 3.0 territory.

## What's deferred to 3.0

These are tempting but explicitly out of 2.0. Park them now so they don't drift back in mid-sprint:

- PHP 8.5 closures-in-attributes / factory providers via attribute arguments.
- NestJS-runtime semantics: interceptors, guards, pipes, exception filters, request-scoped providers.
- A custom compiled-container format beyond PHP-DI's existing compilation.
- Replacement of WordPress hook semantics with a custom event bus.
- Async lifecycle hooks beyond WP-native ones.
- Rewrite of `Container` / `Compiled_Container` / `App_Factory` / `App_Builder`. They work; leave them.
- Integration of the master-line definition helpers (`option()`, `transient()`, `filtered()`, `wrapped()`) into the new definition layer. They stay where they are.

## The discipline contract

v2.0 has failed twice — once as the master "coke-induced epiphany," once as the rewrite/NestJS frenzy. The third attempt holds itself to:

1. **Lock means lock.** From the `2.0.0-beta.1` tag forward: no new public surface. Internal optimizations and bug fixes only. Anything ergonomic or "small" gets parked for 3.0.
2. **Dogfood before ship.** One real production plugin must be ported to 2.0 and run successfully before tagging GA. Until that port is clean, the API isn't done.
3. **3.0 is a parking lot, not a roadmap.** Items deferred above are filed and forgotten. We don't design for them in 2.0.
4. **Frenzy detection.** If a slice grows past its scope, it gets a follow-up bead. We do not extend the in-flight slice.
5. **No two-architectures-at-once.** If a thing is being replaced, the old version is removed in the same slice that introduces the new version. No parallel implementations carrying maintenance cost.

## Audience

These docs are written for: the maintainer (you), a future contributor, and a past-self who has forgotten why a decision was made. They are not marketing. They are not apology. They are the trail of crumbs that lets the next decision be a small one.

## Reading order

- [migration-01-target-architecture.md](migration-01-target-architecture.md) — what we're building
- [migration-02-current-state.md](migration-02-current-state.md) — what's on `beta` today and the gap
- [migration-03-public-api.md](migration-03-public-api.md) — the locked surface
- [migration-04-implementation-plan.md](migration-04-implementation-plan.md) — slice breakdown
- [migration-05-deprecation-and-shipping.md](migration-05-deprecation-and-shipping.md) — release plan
