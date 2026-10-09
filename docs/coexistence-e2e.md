# v1/v2 plugin coexistence

This suite tests unchanged v1 client code when another active WordPress plugin
provides the current beta checkout through Jetpack Autoloader. Version selection
and API compatibility are separate assertions. The current result is **partial
compatibility**, with ten legacy checks requiring reconciliation.

## Running the suite

```bash
composer install
composer test:install
composer test:e2e:install
composer test:e2e
composer test:e2e:strict
```

`test:e2e:install` installs the checked-in fixture lockfiles into ignored
`tests/tmp/coexistence/plugins/`. Run it again after changing fixture entrypoints,
probes, dependencies, or adding classes. Existing fixture source classes and the
beta package are symlinked to this checkout. Do not run Composer directly in the
tracked fixture directories: the beta path repository is relative to the
generated plugin directory.

The ordinary command returns success if all baseline and supported behavior
checks pass and each discrepancy matches a recorded incompatibility. Known
incompatibilities remain PHPUnit **incomplete** results, never passing
compatibility assertions. Unexpected failures remain failures. The strict
command adds `--fail-on-incomplete`, so it exits nonzero until reconciliation is
complete. Fixing a behavior makes its original v1 expectation pass; remove the
corresponding inventory entry and close its issue when verified.

Both commands exercise the same checks. `composer test` continues to run the
unit and integration suites separately. CI also runs the diagnostic e2e suite on
PHP 8.1–8.4 with SQLite and uploads its JSON and JUnit artifacts.

Select subgroups with an explicit suite:

```bash
vendor/bin/phpunit --testsuite=e2e --group=coexistence-autoload --testdox
vendor/bin/phpunit --testsuite=e2e --group=coexistence-startup --testdox
vendor/bin/phpunit --testsuite=e2e --group=coexistence-legacy --testdox
vendor/bin/phpunit --testsuite=e2e --group=coexistence-modern --testdox
vendor/bin/phpunit --testsuite=e2e --group=coexistence-isolation --testdox
```

The whole suite also belongs to `e2e` and `coexistence`. Each legacy probe is a
named PHPUnit dataset, for example `legacy-first-cold / helpers.module_lookup`.

## What runs

| Active plugins | Plugin order | Jetpack discovery |
| --- | --- | --- |
| v1 baseline | Legacy only | Cold, then warm |
| Beta baseline | Modern only | Cold, then warm |
| Mixed | Legacy, modern | Cold, then warm |
| Mixed | Modern, legacy | Cold, then warm |

Each of these eight cases boots real WordPress in a new PHP process. The
controller can load PHPUnit through the repository vendor tree; the request
process never loads that autoloader. WordPress loads the fixture plugins through
its active-plugin option, and each plugin includes its own generated
`vendor/autoload_packages.php`.

The legacy plugin locks DI **1.10.0**. The modern plugin uses **this checkout's
source**, assigned the semantic package version **2.0.0-beta.1** in its path
repository. It does not download the released beta. Both plugins lock Jetpack
Autoloader **5.0.1**, with no `JETPACK_AUTOLOAD_DEV` override. This exercises
ordinary semantic version selection.

Cold requests delete Jetpack's plugin-discovery transient before active plugins
load. The following warm request reads the cache persisted by the previous real
request. Assertions confirm the cache state and expected plugin paths; the cache
is not fabricated. Reflection checks verify the provider of shared functions,
classes, and every exercised DI class/interface/trait, detecting accidental
preloading and fallback to legacy DI classes.

Behavior checks cover deferred startup, initialization on `init`, root and
imported definitions, constructor injection, service reuse, `Infuse`, actions,
filters with arguments, dynamic mappings, container lookup, shared-filter order,
and independent service values in both apps. Legacy public API probes also test
typed container returns, reflective method detection, handler/module helpers,
extension arguments, definition helpers, and positional module construction.

The fixtures disable DI container/hook compilation. Cold/warm in this suite
specifically means **Jetpack discovery**, not DI compiled-cache compatibility.

## Recorded discrepancies

All 21 legacy checks pass under v1 alone. In each mixed case, 11 pass and 10 are
incomplete. The modern baseline and modern behavior in mixed cases pass. There
are 40 incomplete results across the four mixed cases.

| Legacy behavior | Observed with beta selected | Reconciliation issue |
| --- | --- | --- |
| Imported module at its parent's `init:10` priority | Child initialization misses that pass; static definitions remain available | `di-0sy` |
| Typed helper returns `DI\Container` from `xwp_app()` | `TypeError`: beta returns `XWP\DI\App` | `di-qla` |
| `method_exists(xwp_app(...), 'has')` | `false`; magic forwarding does not declare the method | `di-qla` |
| `xwp_get_module()` | Undefined function | `di-1kf` |
| `xwp_register_hook_handler(ClassName::class)` | `TypeError`: beta expects a runtime `Can_Handle` object | `di-1kf` |
| `xwp_extend_app(container:, module:, position:, target:)` | Unknown named parameter `container` | `di-1kf` |
| `XWP\DI\option()` | Undefined function | `di-lz1` |
| `XWP\DI\transient()` | Undefined function | `di-lz1` |
| `XWP\DI\filtered()` | Undefined function | `di-lz1` |
| `new Module($container, $hook, $priority)` | `TypeError`: the second argument is now an integer priority | `di-nfa` |

The legacy child deliberately retains the v1 same-priority declaration. Its
handler is declared independently on the root, so the scheduling discrepancy
does not mask callback coverage. The modern child uses `init:11`, following the
current parent-gated registration contract.

Legacy configuration also emits the expected `xwp_create_app` migration notice.
The harness records it and rejects other notices and PHP errors. No production
API adapters or fixes are included in this test addition.

Use `bd show <issue>` to inspect reconciliation work. The bounded inventory in
`tests/E2E/known-incompatibilities.php` checks observed values or error type and
message before marking a check incomplete; it does not blanket-skip failures.

## Reports and limits

`build/e2e/coexistence.json` contains all scenarios, source paths, captured
lifecycle events, warnings, and probe results. `build/e2e/discrepancies.json`
contains only differing expectations, with their reconciliation issues. Each
request also writes its own JSON report. Owned reports are invalidated before
setup, so an unsuccessful run cannot leave an old aggregate looking current.

SQLite uses `tests/tmp/coexistence/database/`, separate from the integration
database. The e2e WordPress installation uses table prefix `coexist_`; an optional
MySQL run uses the configured test database with that prefix. Run one e2e suite
at a time because its database and report paths are shared. The existing
`WP_CORE_DIR` override is supported; install the matching WordPress/SQLite
environment with `composer test:install` first.

Passing these fixtures does not prove compatibility for every v1 plugin or
release. Immediate application creation, other autoloaders, specialized
REST/AJAX/CLI consumers, custom runtime decorators, DI cache formats, multisite,
and real downstream plugin packages need their own cases. Jetpack's loading
timing and discovery caveats are documented in its
[autoloader guide](https://developer.jetpack.com/docs/jetpack-development/autoloader/).
