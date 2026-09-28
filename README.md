<div align="center">

<h1 align="center" style="border-bottom: none; margin-bottom: 0px">XWP-DI</h1>
<h3 align="center" style="margin-top: 0px">Dependency Injection Container for WordPress</h3>

[![Packagist Version](https://img.shields.io/packagist/v/x-wp/di?label=Release&style=flat-square)](https://packagist.org/packages/x-wp/di)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/x-wp/di/php?label=PHP&logo=php&logoColor=white&logoSize=auto&style=flat-square)
![Static Badge](https://img.shields.io/badge/WP-%3E%3D6.4-3858e9?style=flat-square&logo=wordpress&logoSize=auto)
[![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/x-wp/di/release.yml?label=Build&event=push&style=flat-square&logo=githubactions&logoColor=white&logoSize=auto)](https://github.com/x-wp/di/actions/workflows/release.yml)

</div>

This library allows you to implement [dependency injection design pattern](https://en.wikipedia.org/wiki/Dependency_injection) in your WordPress plugin or theme. It provides a simple and easy-to-use interface to manage dependencies and hook callbacks.

## Key Features

1. Reliable - Powered by [PHP-DI](https://php-di.org/), a mature and feature-rich dependency injection container.
2. Interoperable - Provides PSR-11 compliant container interface.
3. Easy to use - Reduces the boilerplate code required to manage dependencies and hook callbacks.
4. Customizable - Allows various configuration options to customize the container behavior.
5. Flexible - Enables advanced hook callback mechanisms.
6. Fast - Dependencies are resolved only when needed, and the container can be compiled for better performance.

## Installation

You can install this package via composer:

```bash
composer require x-wp/di
```

> [!TIP]
> We recommend using the `automattic/jetpack-autoloader` with this package to prevent autoloading issues.

## Usage

Below is a simple example to demonstrate how to use this library in your plugin or theme.

### Creating the Application and Container

You will need a class which will be used as the entry point for your plugin/theme. This class must have a `#[Module]` attribute to define the container configuration.

```php
<?php

use XWP\DI\Decorators\Module;

#[Module(
    hook: 'plugins_loaded', // Hook to initialize the module
    priority: 10,           // Hook priority
    imports: array(),       // List of classnames imported by this module
    handlers: array(),      // List of classnames which are used as handlers
)]
class My_Plugin {
    /**
     * Returns the PHP-DI container definition.
     *
     * @see https://php-di.org/doc/php-definitions.html
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        return array(
            'my.def' => \DI\value('my value'),
        );
    }
}
```

In your plugin bootstrap, schedule creation with `xwp_load_app()`. It returns a
boolean and defers application class loading until `plugins_loaded`, so Jetpack
Autoloader can select dependency versions first.

```php
<?php

xwp_load_app(
    array(
        'app_id'     => 'my-plugin',
        'app_module' => My_Plugin::class,
        'app_file'   => __FILE__,
        'cache_app'  => false,
    ),
);
```

After creation, `xwp_app( 'my-plugin' )` returns the registered `XWP\DI\App`.
Use `$app->container()` to access its dependency container. Services can inject
`App` or resolve `xwp.app`; both refer to that same application instance.

For synchronous creation once autoloading is safe, use
`$app = xwp_create_app( $config )`, followed by `$app->run()`. The internal root
module supplies framework definitions and imports your configured module.

### Using handlers and callbacks

Handler is any class which is annotated with a `#[Handler]` attribute. Class methods can be annotated with `#[Action]` or `#[Filter]` attributes to define hook callbacks.

```php
<?php

use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;

#[Handler(
    tag: 'init',
    priority: 20,
    container: 'my-plugin',
    context: Handler::CTX_FRONTEND,
)]
class My_Handler {
    #[Filter( tag: 'body_class', priority: 10 )]
    public function change_body_class( array $classes ): array {
        $classes[] = 'my-class';

        return $classes;
    }

    #[Action( tag: 'wp_enqueue_scripts', priority: 10 )]
    public function enqueue_scripts(): void {
        wp_enqueue_script('my-script', 'path/to/my-script.js', array(), '1.0', true);
    }
}
```

### Examples

You can find the examples in the [examples](https://github.com/x-wp/di/tree/master/examples) directory.

## Testing

The test harness pairs PHPUnit with `wp-phpunit` and SQLite Database Integration. No Docker or database server is needed for the default workflow. Two suites are wired up:

- **Unit** — fast, no WordPress. Uses `Brain\Monkey` to stub WP functions; covers the DI container, decorators, and reflection helpers.
- **Integration** — boots a real WordPress install via `wp-phpunit` against SQLite and asserts the decorator-driven hooks actually register.

### Prerequisites

- PHP 8.1–8.4 with the `pdo_sqlite` and `zip` extensions (SQLite 3.37.0 or newer)
- Composer
- `tar` for extracting WordPress core

### One-time setup

```bash
composer install
composer test:install   # downloads WP core + the pinned SQLite plugin; resets the test DB
```

### Running tests

```bash
composer test                # both suites
composer test:unit           # unit suite only (no WordPress or SQLite needed)
composer test:integration    # integration suite only
composer test:coverage       # HTML coverage at build/coverage/html/index.html
```

SQLite uses a disposable file under `tests/tmp/database/`, shared by the WordPress install subprocess and PHPUnit. The test bootstrap rebuilds WordPress's tables on each run. Rerun `composer test:install` to remove the SQLite database and its journal files entirely. Run only one integration suite at a time per database directory.

### Optional MySQL testing

To check MySQL compatibility, install PHP's `pdo_mysql` and `mysqli` extensions and point the tests at a disposable MySQL database. The existing Docker service remains available:

```bash
composer test:up
WP_TESTS_DB_ENGINE=mysql composer test:install
WP_TESTS_DB_ENGINE=mysql composer test:integration
composer test:down
```

Use `WP_TESTS_DB_ENGINE=mysql` for both setup and testing. The installer drops and recreates the configured MySQL database. SQLite and MySQL can share the downloaded WordPress core; the test drop-in loads SQLite only when selected.

### Configuration

All defaults are baked in but every value is overridable via env var:

| Variable                | Default              | Notes                                      |
| ----------------------- | -------------------- | ------------------------------------------ |
| `WP_TESTS_DB_ENGINE`    | `sqlite`             | `sqlite` or `mysql`                         |
| `WP_TESTS_SQLITE_DIR`   | `tests/tmp/database` | SQLite directory; use an absolute path for overrides |
| `WP_TESTS_DB_HOST`      | `127.0.0.1:33076`    | Test database host (port `33076` avoids local MySQL and DDEV) |
| `WP_TESTS_DB_NAME`      | `wp_phpunit_tests`   |                                            |
| `WP_TESTS_DB_USER`      | `root`               |                                            |
| `WP_TESTS_DB_PASSWORD`  | `root`               |                                            |
| `WP_CORE_DIR`           | `tests/tmp/wordpress`| Where `install-tests.php` extracts WP core |
| `WP_VERSION`            | `latest`             | Pin to e.g. `6.4` to test against an older release |

The `WP_TESTS_DB_HOST`, `WP_TESTS_DB_USER`, and `WP_TESTS_DB_PASSWORD` settings apply only to MySQL. SQLite Database Integration is pinned to version 3.0.2 in the installer. GitHub Actions runs SQLite on PHP 8.1–8.4 and retains a MySQL compatibility job on PHP 8.3.

## Documentation

For more information, please refer to the [official documentation](https://extended.wp.rs/dependency-injection).
