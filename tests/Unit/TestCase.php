<?php
/**
 * Base TestCase for the unit suite.
 *
 * Wires Brain\Monkey + Mockery so unit tests can stub WP functions
 * without booting WordPress.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Unit;

use Brain\Monkey;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

abstract class TestCase extends PolyfillTestCase {
    protected function set_up(): void {
        parent::set_up();
        Monkey\setUp();
    }

    protected function tear_down(): void {
        Monkey\tearDown();
        parent::tear_down();
    }
}
