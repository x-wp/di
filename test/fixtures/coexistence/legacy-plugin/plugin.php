<?php
/**
 * Plugin Name: DI Coexistence Legacy Fixture
 * Description: A client written against x-wp/di v1.10.0.
 * Version: 1.0.0
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DI\E2E\Legacy;

$GLOBALS['xwp_di_e2e_plugin_order'][] = 'legacy';

require_once __DIR__ . '/vendor/autoload_packages.php';
require_once __DIR__ . '/probes.php';

\xwp_load_app(
    array(
        'id'         => 'coexist_legacy',
        'module'     => Root_Module::class,
        'attributes' => true,
        'autowiring' => true,
        'compile'    => false,
        'proxies'    => false,
    ),
);
