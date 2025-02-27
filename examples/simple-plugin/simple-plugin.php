<?php
/**
 * Plugin Name: Simple plugin
 * Description: A simple plugin to demonstrate the use of the DI container.
 * Version:     1.0.0
 *
 * @package Example
 */

defined( 'ABSPATH' ) || exit;

defined( 'XWPEX_FILE' ) || define( 'XWPEX_FILE', __FILE__ );
defined( 'XWPEX_BASE' ) || define( 'XWPEX_BASE', \plugin_basename( XWPEX_FILE ) );
defined( 'XWPEX_PATH' ) || define( 'XWPEX_PATH', \plugin_dir_path( XWPEX_FILE ) );

require __DIR__ . '/vendor/autoload_packages.php';

/**
 * This loads the main application module.
 *
 * We use the `xwp_load_app` function to do all the heavy lifting.
 * You can also use `xwp_create_app` to create a new container immediately.
 * However this has caveats.
 *
 * If you use the `jetpack-autloader` autoloading will be done on the `plugins_loaded` hook.
 * Using `xwp_create_app` will immediately start autoloading the classes and dependencies, which will prevent latest module versions from being loaded.
 *
 * @see https://github.com/Automattic/jetpack-autoloader
 */
xwp_load_app(
    array(
        'autowiring'  => true,
        'compile'     => 'production' === wp_get_environment_type(),
        'compile_dir' => XWPEX_PATH . 'cache',
        'id'          => 'example',
        'module'      => Example\App::class,
    ),
    hook: 'plugins_loaded',
    priority: 1,
);
