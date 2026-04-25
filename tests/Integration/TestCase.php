<?php
/**
 * Base TestCase for the integration suite.
 *
 * Loaded after wp-phpunit's bootstrap, so WP_UnitTestCase is defined.
 * Subclasses can use the full WP testing API (factories, has_action,
 * apply_filters, etc.).
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use WP_UnitTestCase;

abstract class TestCase extends WP_UnitTestCase {}
