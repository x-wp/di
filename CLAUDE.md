# AI Agent Instructions

## Repo

`x-wp/di`: WordPress DI library for PHP `>=8.0`. Builds PHP-DI containers and registers WP hooks/callbacks via PHP 8 attributes on modules/handlers. Current checkout is the lightweight `master` runtime, not the alpha/parser beta architecture. Preserve public compatibility unless explicitly told otherwise.

## Map

- `src/App_*`, `src/Invoker.php`, `src/Handler_Factory.php`: container/bootstrap/invocation core.
- `src/Decorators/`: attrs: `Module`, `Handler`, `Action`, `Filter`, `REST_*`, `Ajax_*`, `CLI_*`, `Infuse`.
- `src/Definition/`: PHP-DI definition helpers, including `XWP\DI\module()` and `filtered()`.
- `src/Injector/`: hook/module refs and executor helpers.
- `src/Core/`: classmapped WP-facing bases (`Context`, `REST_Controller`, `CLI_Namespace`).
- `src/Functions/`: Composer-loaded public helpers.
- `src/Interfaces/`, `src/Traits/`, `src/Utils/Reflection.php`: contracts/shared/reflection support.
- `tests/`: PHPUnit unit + WP integration tests. `test/fixtures/shared/`: dev fixture namespace `XWP\DI\T\`.
- `examples/`: public usage samples. Treat `src/` as source of truth.
- `docs/state-*.md`: branch comparison notes only.

## Rules

- Architecture: `xwp_load_app()`/`xwp_create_app()` -> `App_Factory` -> `App_Builder` -> `xwp_register_module()` -> `Invoker`/`Handler_Factory` -> decorators/interfaces -> WP hooks.
- Public API: decorators, helper functions, container IDs/tokens, definition helpers, and hook semantics are externally consumed.
- Style: follow nearby code, WP + Oblak rules, `array(...)`, guard clauses, typed props, union types, named args, template/array-shape phpdoc, `class-string` annotations.
- Names: namespace `XWP\\DI\\*`; keep WP-style underscores and snake_case. Do not normalize names.
- Risk: `Decorators/Hook.php`, `Decorators/Handler.php`, `Decorators/Module.php`, `Invoker.php`, `Handler_Factory.php`, `App_*`, `Definition/*`, dynamic tags, container keys, WP context/init timing.
- Avoid: broad refactors, opportunistic renames, mass `array(...)` -> `[]`, deleting suppressions without proof, breaking helper names/tokens.
- Avoid beta assumptions: do not reintroduce alpha-only `src/Hook/Parser`, `Hook\Factory`, `Hook\Compiler`, custom `Container`, `Compiled_Container`, or `Container::run()` unless requested.

## Workflow

- Read nearby producer and consumer paths before edits, especially for hooks/decorators/container definitions.
- Prefer the smallest backward-compatible change that matches local style.
- For behavior changes, run focused tests/static checks plus impacted fixture/example checks. For docs, verify against repo state.
- Useful commands: `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`, `vendor/bin/phpcs`.

<!-- BEGIN BEADS INTEGRATION v:1 profile:minimal hash:ca08a54f -->
## Beads Issue Tracker

This project uses **bd (beads)** for issue tracking. Run `bd prime` to see full workflow context and commands.

### Quick Reference

```bash
bd ready              # Find available work
bd show <id>          # View issue details
bd update <id> --claim  # Claim work
bd close <id>         # Complete work
```

### Rules

- Use `bd` for ALL task tracking — do NOT use TodoWrite, TaskCreate, or markdown TODO lists
- Run `bd prime` for detailed command reference and session close protocol
- Use `bd remember` for persistent knowledge — do NOT use MEMORY.md files

## Session Completion

**When ending a work session**, you MUST complete ALL steps below. Work is NOT complete until `git push` succeeds.

**MANDATORY WORKFLOW:**

1. **File issues for remaining work** - Create issues for anything that needs follow-up
2. **Run quality gates** (if code changed) - Tests, linters, builds
3. **Update issue status** - Close finished work, update in-progress items
4. **PUSH TO REMOTE** - This is MANDATORY - Unless on **MASTER** branch:
   ```bash
   git pull --rebase
   bd dolt push
   git push  # Must not run on master
   git status  # MUST show "up to date with origin"
   ```
5. **Clean up** - Clear stashes, prune remote branches
6. **Verify** - All changes committed AND pushed
7. **Hand off** - Provide context for next session

**CRITICAL RULES:**
- Work is NOT complete until `git push` succeeds, except on MASTER branch
- NEVER stop before pushing - that leaves work stranded locally
- NEVER say "ready to push when you are" - YOU must push
- If push fails, resolve and retry until it succeeds
- 
<!-- END BEADS INTEGRATION -->
