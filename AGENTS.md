# AI Agent Instructions

`AGENTS.md` and `CLAUDE.md` carry the same repository instructions. Keep them identical when editing either file; verify with `cmp AGENTS.md CLAUDE.md`.

## Repo

`x-wp/di`: WordPress DI library for PHP `>=8.1 <8.5` with PHP-DI `^7.1` (see `composer.json`). Registers WP hooks/callbacks via PHP attributes on modules/handlers. These instructions describe the `beta` architecture, where the definition migration is in progress. Check the current branch and source before assuming a migration step is complete. Preserve public compatibility unless explicitly told otherwise.

## Map

- `src/App.php`, `src/App_*`: application lifecycle, factory, and container builder.
- `src/Container.php`, `src/Compiled_Container.php`, `src/Invoker.php`: dependency containers and handler/hook orchestration.
- `src/Hook/`: `Parser`, `Factory`, and `Compiler` for hook metadata, runtime objects, and caching.
- `src/Decorators/`: attributes: `Module`, `Handler`, `Action`, `Filter`, `Dynamic_*`, `REST_*`, `Ajax_*`, `CLI_*`, `Infuse`.
- `src/Definition/`: module, handler, callback, and service definitions; `Helper/` contains the helper behind `XWP\DI\module()`.
- `src/Core/Modules/Internal_Root_Module.php`: internal root composition and base container definitions.
- `src/Global/`: classmapped WP-facing classes (`XWP_Context`, `XWP_REST_Controller`, `XWP_CLI_Namespace`).
- `src/Functions/`: Composer-loaded public helpers.
- `src/Interfaces/`, `src/Traits/`, `src/Utils/Reflection.php`: contracts/shared/reflection support.
- `tests/Unit/`, `tests/Integration/`: PHPUnit unit and WP integration tests, namespace `Tests\XWP\DI\`.
- `test/fixtures/shared/`: dev fixture namespace `XWP\DIT\`. `test/fixtures/di-plugin/`: WP integration plugin.
- `examples/`: public usage samples. Treat `src/` as source of truth.
- `docs/migration-*.md`, `docs/definition-split-plan.md`: migration plans and historical snapshots; verify implementation claims against `src/` and tests.

## Rules

- Bootstrap: `xwp_create_app()` -> `App_Factory` -> `App_Builder` -> container -> `App`. `xwp_app()` also returns `App`.
- Startup: `xwp_load_app()` schedules creation and `App::run()` on a WP hook. `App::run()` registers the root module through the container and `Invoker`; `Hook\Factory` resolves runtime handlers/callbacks. Keep creation and startup timing distinct.
- Public API: decorators, helper functions, container IDs/tokens, definition helpers, and hook semantics are externally consumed.
- Style: follow nearby code, WP + Oblak rules, `array(...)`, guard clauses, typed props, union types, named args, template/array-shape phpdoc, `class-string` annotations.
- Names: namespace `XWP\DI\*`, except the classmapped global classes; keep existing WP-style underscores, snake_case, and PHP-DI method names. Do not normalize names.
- Risk: `Decorators/Hook.php`, `Decorators/Handler.php`, `Decorators/Module.php`, `Hook/*`, `Invoker.php`, `App.php`, `App_*`, `Container.php`, `Compiled_Container.php`, `Definition/*`, dynamic tags, container keys, cache formats, WP context/init timing.
- Avoid: broad refactors, opportunistic renames, mass `array(...)` -> `[]`, deleting suppressions without proof, breaking helper names/tokens.
- Avoid branch assumptions: the parser/compiler and custom containers are part of this checkout. Do not restore removed master-only paths or implement future migration architecture unless requested.

## Workflow

- Read nearby producer and consumer paths before edits, especially for hooks/decorators/container definitions.
- Inspect `git status` before editing; preserve unrelated changes and existing stashes. Stage only files belonging to the task.
- Prefer the smallest backward-compatible change that matches local style.
- For behavior changes, run focused tests/static checks plus impacted fixture/example checks. For docs, verify against repo state.
- Tests: `composer test:unit`, `composer test:integration`, or `composer test` for both. Use explicit PHPUnit suites because `tests/bootstrap.php` selects the WP bootstrap from the command arguments.
- Integration setup: `composer test:install` uses SQLite without Docker. For optional MySQL, run `composer test:up`, then set `WP_TESTS_DB_ENGINE=mysql` for both installation and integration tests (see `tests/wp-tests-config.php`).
- Static checks: `vendor/bin/phpstan analyse` and `vendor/bin/phpcs`.

<!-- BEGIN BEADS INTEGRATION v:1 profile:minimal hash:ca08a54f -->
## Beads Issue Tracker

This project uses **bd (beads)** for issue tracking. Run `bd prime` to see full workflow context and commands.

### Quick Reference

```bash
bd ready               # Find available work
bd show <id>           # View issue details
bd update <id> --claim  # Claim work
bd close <id>          # Complete work
```

### Rules

- Use `bd` for ALL task tracking — do NOT use TodoWrite, TaskCreate, or markdown TODO lists
- Run `bd prime` for detailed command reference and session close protocol
- Use `bd remember` for persistent knowledge — do NOT use MEMORY.md files

## Session Completion

1. **File follow-up issues** for remaining work.
2. **Run relevant quality gates** for code changes; verify documentation against the repo and compare both instruction files.
3. **Update issue status**: close finished work and update in-progress items.
4. **Commit task changes and sync**. On branches other than `master`, push is mandatory:

   ```bash
   git pull --rebase
   bd dolt push
   git push
   git status  # Must show "up to date with origin"
   ```

   Do not push `master`. This repository authorizes commit/sync/push on other branches, overriding the conservative default from `bd prime`. Preserve unrelated work when rebasing.
5. **Clean up task-created temporary state** and prune stale remote references. Do not clear pre-existing stashes or delete unrelated work.
6. **Verify and hand off**: task changes must be committed and, except on `master`, pushed. Report validation, remaining work, and any blocker. If a push fails, resolve and retry; do not report completion while it remains blocked.

<!-- END BEADS INTEGRATION -->
