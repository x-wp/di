<?php
/**
 * Plugin Name:      Simple plugin - Pro
 * Description:      Pro version of the simple plugin - demonstrates extending the simple plugin.
 * Version:          1.0.0
 * Requires Plugins: example-plugin
 *
 * @package Example
 */

defined( 'ABSPATH' ) || exit;

defined( 'XWPEXP_FILE' ) || define( 'XWPEXP_FILE', __FILE__ );
defined( 'XWPEXP_BASE' ) || define( 'XWPEXP_BASE', \plugin_basename( XWPEXP_FILE ) );
defined( 'XWPEXP_PATH' ) || define( 'XWPEXP_PATH', \plugin_dir_path( XWPEXP_FILE ) );

require __DIR__ . '/vendor/autoload_packages.php';

/**
 * We can extend the simple plugin by extending the container with an additional module.
 */
xwp_extend_app(
    target_app: 'example',          // Container ID we want to extend.
    module: ExamplePro\App::class, // Entry module.
    target: Example\App::class,    // Which module we want to extend.
);
