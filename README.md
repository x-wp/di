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
    container: 'my-plugin', // Unique identifier for the container
    hook: 'plugins_loaded', // Hook to initialize the a
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

After defining the module, you can create the application using the `xwp_create_app` function.

```php
<?php

xwp_create_app(
    array(
        'id' => 'my-plugin',
        'module' => My_Plugin::class,
        'compile' => false,
    );
);

```

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

## Documentation

For more information, please refer to the [official documentation](https://extended.wp.rs/dependency-injection).
