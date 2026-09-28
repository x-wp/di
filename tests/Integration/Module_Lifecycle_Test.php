<?php
/**
 * Module definitions, context cascades, initialization, and scheduling.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Compiled_Container;
use XWP\DI\Decorators\Module;
use XWP\DIT\Lifecycle\Lifecycle_Child_Service;
use XWP\DIT\Lifecycle\Lifecycle_Excluded_Service;
use XWP\DIT\Lifecycle\Lifecycle_Parent_Service;
use XWP\DIT\Lifecycle\Module_Lifecycle_Root;

final class Module_Lifecycle_Test extends TestCase {
    private string $cache_dir;
    private int $original_context;
    private \ReflectionProperty $context;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-module-lifecycle-' . uniqid();
        mkdir( $this->cache_dir );
        $this->original_context = \XWP_Context::get();
        $this->context = new \ReflectionProperty( \XWP_Context::class, 'current' );
        $this->context->setAccessible( true );
        $this->context->setValue( null, Module::CTX_ADMIN );
        $this->reset_lifecycle();
    }

    public function tear_down(): void {
        $this->reset_lifecycle();
        $this->context->setValue( null, $this->original_context );
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        rmdir( $this->cache_dir );
        parent::tear_down();
    }

    /** @dataProvider cache_modes */
    public function test_context_cascades_without_removing_definitions_from_shared_caches( bool $compile, bool $hooks ): void {
        $config = $this->config( $compile, $hooks );

        // Build in an excluded context, reuse in an eligible one, then exclude again.
        foreach ( array( Module::CTX_FRONTEND, Module::CTX_ADMIN, Module::CTX_FRONTEND ) as $context ) {
            $this->context->setValue( null, $context );
            $app = $this->build_app( $config );
            $this->assert_definitions_available( $app );
            self::assertSame( array(), Module_Lifecycle_Root::$events );
            $app->run();
            self::assertSame( array(), Module_Lifecycle_Root::$events );
            self::assertFalse( $app->container()->has( 'lifecycle.parent.runtime' ) );

            do_action( 'xwp_module_parent' );
            do_action( 'xwp_module_child' );
            do_action( 'xwp_module_excluded' );
            do_action( 'xwp_module_descendant' );

            if ( Module::CTX_FRONTEND === $context ) {
                self::assertSame( array(), Module_Lifecycle_Root::$events, 'Excluded ancestors block construction, conditions, and initialization throughout their subtree.' );
                self::assertFalse( has_action( 'xwp_module_parent' ) );
                self::assertFalse( has_action( 'xwp_module_child' ) );
                self::assertFalse( has_filter( 'xwp_module_value' ) );
                self::assertSame( 'value', apply_filters( 'xwp_module_value', 'value' ) );
                do_action( 'xwp_module_action' );
                self::assertSame( array(), Module_Lifecycle_Root::$events );
                self::assertFalse( $app->container()->has( 'lifecycle.parent.runtime' ) );
                self::assertFalse( $app->container()->has( 'lifecycle.child.runtime' ) );
            } else {
                self::assertSame( array(
                    'parent:condition', 'parent:construct', 'parent:configure', 'parent:initialize',
                    'parent-handler:construct', 'parent-handler:initialize',
                    'child:condition', 'child:construct', 'child:configure', 'child:initialize',
                    'child-handler:construct', 'child-handler:initialize',
                ), Module_Lifecycle_Root::$events );
                self::assertTrue( $app->container()->get( 'lifecycle.parent.runtime' ) );
                self::assertTrue( $app->container()->get( 'lifecycle.child.runtime' ) );
                self::assertSame( 'value:parent-handler:parent:child-handler:child', apply_filters( 'xwp_module_value', 'value' ) );
                do_action( 'xwp_module_action' );
                self::assertSame( array( 'parent:action', 'child:action' ), array_slice( Module_Lifecycle_Root::$events, -2 ) );
            }

            self::assertFalse( has_action( 'xwp_module_excluded' ), 'An imported module must also satisfy its own context.' );
            self::assertFalse( has_action( 'xwp_module_descendant' ), 'A global descendant cannot override its excluded ancestor.' );
            self::assertFalse( $app->container()->has( 'lifecycle.excluded.runtime' ) );
            self::assertFalse( $app->container()->has( 'lifecycle.descendant.runtime' ) );
            $this->assert_definitions_available( $app );
            $this->reset_lifecycle();
        }
    }

    /** @dataProvider cache_modes */
    public function test_initialization_finishes_before_children_are_registered_on_their_own_schedule( bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            $app->run();
            self::assertFalse( has_action( 'xwp_module_child' ) );
            $boundaries = array();
            add_action( 'xwp_module_parent', static function () use ( &$boundaries ): void {
                self::assertSame( array(), Module_Lifecycle_Root::$events );
                $boundaries[] = 'before parent';
            }, 11 );
            add_action( 'xwp_module_parent', static function () use ( &$boundaries ): void {
                self::assertSame( array(
                    'parent:condition', 'parent:construct', 'parent:configure', 'parent:initialize',
                    'parent-handler:construct', 'parent-handler:initialize',
                ), Module_Lifecycle_Root::$events );
                self::assertTrue( has_action( 'xwp_module_child' ) );
                self::assertSame( 'value:parent-handler:parent', apply_filters( 'xwp_module_value', 'value' ) );
                $boundaries[] = 'after parent';
            }, 13 );
            do_action( 'xwp_module_parent' );

            $before_child = Module_Lifecycle_Root::$events;
            add_action( 'xwp_module_child', static function () use ( $before_child, &$boundaries ): void {
                self::assertSame( $before_child, Module_Lifecycle_Root::$events );
                $boundaries[] = 'before child';
            }, 6 );
            add_action( 'xwp_module_child', static function () use ( $before_child, &$boundaries ): void {
                self::assertSame( array_merge( $before_child, array(
                    'child:condition', 'child:construct', 'child:configure', 'child:initialize',
                    'child-handler:construct', 'child-handler:initialize',
                ) ), Module_Lifecycle_Root::$events );
                $boundaries[] = 'after child';
            }, 8 );
            do_action( 'xwp_module_child' );
            self::assertSame( array( 'before parent', 'after parent', 'before child', 'after child' ), $boundaries );
            self::assertSame( 'value:parent-handler:parent:child-handler:child', apply_filters( 'xwp_module_value', 'value' ) );
        }
    }

    /** @dataProvider cache_modes */
    public function test_rejected_module_initialization_preserves_definitions_and_retries_on_the_next_hook( bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            Module_Lifecycle_Root::$allowed['parent'] = false;
            $app->run();
            self::assertSame( array(), Module_Lifecycle_Root::$events, 'Initialization conditions belong to the scheduled lifecycle point.' );
            $this->assert_definitions_available( $app );
            do_action( 'xwp_module_parent' );
            do_action( 'xwp_module_child' );
            self::assertSame( array( 'parent:condition' ), Module_Lifecycle_Root::$events );
            self::assertFalse( has_action( 'xwp_module_child' ) );
            self::assertFalse( has_filter( 'xwp_module_value' ) );
            self::assertFalse( has_action( 'xwp_module_action' ) );
            self::assertFalse( $app->container()->has( 'lifecycle.parent.runtime' ) );
            self::assertFalse( $app->container()->get( 'Hook-' . Module_Lifecycle_Root::class )->is_loaded() );

            Module_Lifecycle_Root::$allowed['parent'] = true;
            do_action( 'xwp_module_parent' );
            self::assertSame( array(
                'parent:condition', 'parent:condition', 'parent:construct', 'parent:configure', 'parent:initialize',
                'parent-handler:construct', 'parent-handler:initialize',
            ), Module_Lifecycle_Root::$events );
            self::assertTrue( has_action( 'xwp_module_child' ) );
            $events = Module_Lifecycle_Root::$events;
            Module_Lifecycle_Root::$allowed['parent'] = false;
            do_action( 'xwp_module_parent' );
            self::assertSame( $events, Module_Lifecycle_Root::$events, 'Successful module initialization is retained.' );
            self::assertSame( 'value:parent-handler:parent', apply_filters( 'xwp_module_value', 'value' ) );
            $this->assert_definitions_available( $app );
        }
    }

    /** @dataProvider cache_modes */
    public function test_registration_after_a_completed_hook_waits_for_its_next_occurrence( bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            do_action( 'xwp_module_parent' );
            $app->run();
            self::assertSame( array(), Module_Lifecycle_Root::$events, 'Starting after a completed hook must not catch up immediately.' );
            self::assertFalse( has_filter( 'xwp_module_value' ) );
            do_action( 'xwp_module_parent' );
            self::assertSame( 'value:parent-handler:parent', apply_filters( 'xwp_module_value', 'value' ) );
        }
    }

    /** @dataProvider cache_modes */
    public function test_registration_after_the_priority_has_passed_waits_for_the_next_occurrence( bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            $start = static function () use ( $app ): void {
                $app->run();
            };
            add_action( 'xwp_module_parent', $start, 20 );
            do_action( 'xwp_module_parent' );
            self::assertSame( array(), Module_Lifecycle_Root::$events, 'A module scheduled at 12 must not catch up when registered at 20.' );
            self::assertFalse( has_filter( 'xwp_module_value' ) );
            remove_action( 'xwp_module_parent', $start, 20 );
            do_action( 'xwp_module_parent' );
            self::assertSame( 'value:parent-handler:parent', apply_filters( 'xwp_module_value', 'value' ) );
        }
    }

    /** @dataProvider cache_modes */
    public function test_rejected_import_retries_without_disabling_its_parent( bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            $app->run();
            do_action( 'xwp_module_parent' );
            $parent_events = Module_Lifecycle_Root::$events;
            Module_Lifecycle_Root::$allowed['child'] = false;
            do_action( 'xwp_module_child' );
            self::assertSame( array_merge( $parent_events, array( 'child:condition' ) ), Module_Lifecycle_Root::$events );
            self::assertFalse( $app->container()->has( 'lifecycle.child.runtime' ) );
            self::assertSame( 'value:parent-handler:parent', apply_filters( 'xwp_module_value', 'value' ) );
            do_action( 'xwp_module_action' );
            self::assertSame( array( 'parent-handler:invoke', 'parent:invoke', 'parent:action' ), array_slice( Module_Lifecycle_Root::$events, -3 ) );
            $this->assert_definitions_available( $app );

            $before_retry = Module_Lifecycle_Root::$events;
            Module_Lifecycle_Root::$allowed['child'] = true;
            do_action( 'xwp_module_child' );
            self::assertSame( array_merge( $before_retry, array(
                'child:condition', 'child:construct', 'child:configure', 'child:initialize',
                'child-handler:construct', 'child-handler:initialize',
            ) ), Module_Lifecycle_Root::$events );
            self::assertTrue( $app->container()->get( 'lifecycle.child.runtime' ) );
            self::assertSame( 'value:parent-handler:parent:child-handler:child', apply_filters( 'xwp_module_value', 'value' ) );
        }
    }

    /** @dataProvider cache_modes */
    public function test_import_registered_after_its_hook_waits_for_the_next_occurrence( bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            $app->run();
            do_action( 'xwp_module_child' );
            do_action( 'xwp_module_parent' );
            self::assertSame( array(
                'parent:condition', 'parent:construct', 'parent:configure', 'parent:initialize',
                'parent-handler:construct', 'parent-handler:initialize',
            ), Module_Lifecycle_Root::$events );
            self::assertTrue( has_action( 'xwp_module_child' ) );
            self::assertFalse( $app->container()->has( 'lifecycle.child.runtime' ) );
            self::assertSame( 'value:parent-handler:parent', apply_filters( 'xwp_module_value', 'value' ) );

            do_action( 'xwp_module_child' );
            self::assertTrue( $app->container()->get( 'lifecycle.child.runtime' ) );
            self::assertSame( 'value:parent-handler:parent:child-handler:child', apply_filters( 'xwp_module_value', 'value' ) );
        }
    }

    public static function cache_modes(): array {
        return array(
            'uncached' => array( false, false ),
            'hooks' => array( false, true ),
            'container' => array( true, false ),
            'both' => array( true, true ),
        );
    }

    private function assert_definitions_available( App $app ): void {
        foreach ( array( 'parent', 'child', 'excluded', 'descendant' ) as $label ) {
            self::assertSame( $label, $app->container()->get( 'lifecycle.' . $label . '.definition' ) );
        }
        // Resolution alone could pass through implicit autowiring even if declarations vanished.
        $entries = $app->container()->getKnownEntryNames();
        foreach ( array( Lifecycle_Parent_Service::class, Lifecycle_Child_Service::class, Lifecycle_Excluded_Service::class ) as $service ) {
            self::assertContains( $service, $entries );
            self::assertInstanceOf( $service, $app->container()->get( $service ) );
        }
    }

    private function apps( bool $compile, bool $hooks ): \Generator {
        $config = $this->config( $compile, $hooks );
        foreach ( array( 'cold', 'warm' ) as $pass ) {
            $this->reset_lifecycle();
            yield $pass => $this->build_app( $config );
        }
    }

    private function build_app( array $config ): App {
        $app = App_Builder::configure( $config )->build()->get( App::class );
        self::assertSame( $config['cache_app'], $app->container() instanceof Compiled_Container );
        self::assertSame( $config['cache_hooks'], file_exists( $this->cache_dir . '/hook-definition.php' ) );
        return $app;
    }

    private function reset_lifecycle(): void {
        foreach ( array( 'xwp_module_parent', 'xwp_module_child', 'xwp_module_excluded', 'xwp_module_descendant', 'xwp_module_value', 'xwp_module_action' ) as $hook ) {
            remove_all_filters( $hook );
        }
        Module_Lifecycle_Root::$events = array();
        Module_Lifecycle_Root::$allowed = array();
    }

    private function config( bool $compile, bool $hooks ): array {
        $id = uniqid( 'module_lifecycle_' );

        return array(
            'app_id' => $id,
            'app_class' => 'Compiled' . $id,
            'app_module' => Module_Lifecycle_Root::class,
            'app_file' => false,
            'app_type' => 'plugin',
            'app_debug' => false,
            'app_preload' => false,
            'app_version' => '1.0.0',
            'cache_app' => $compile,
            'cache_defs' => false,
            'cache_hooks' => $hooks,
            'cache_dir' => $this->cache_dir,
            'extendable' => false,
            'public' => false,
            'use_attributes' => true,
            'use_autowiring' => true,
            'use_proxies' => false,
        );
    }
}
