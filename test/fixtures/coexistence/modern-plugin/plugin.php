<?php
/**
 * Plugin Name: DI Coexistence Modern Fixture
 * Description: A client written against the current x-wp/di beta API.
 * Version: 2.0.0
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DI\E2E\Modern;

$GLOBALS['xwp_di_e2e_plugin_order'][] = 'modern';

require_once __DIR__ . '/vendor/autoload_packages.php';
require_once __DIR__ . '/probes.php';

\xwp_load_app(
    array(
        'app_id'      => 'coexist_modern',
        'app_module'  => Root_Module::class,
        'app_debug'   => false,
        'app_preload' => false,
        'cache_app'   => false,
        'cache_defs'  => false,
        'cache_hooks' => false,
    ),
);
