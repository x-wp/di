<?php
/**
 * Pure-reflection unit test: the decorator attributes on the shared
 * Body_Handler fixture are readable and carry the expected metadata.
 *
 * This test does not boot WordPress and does not call into the DI
 * container; it just proves the attribute surface is reflectable.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Unit\Decorators;

use ReflectionClass;
use Tests\XWP\DI\Unit\TestCase;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DIT\Core\Handlers\Body_Handler;

final class Action_Reflection_Test extends TestCase {
    public function test_handler_attribute_on_body_handler(): void {
        $attrs = ( new ReflectionClass( Body_Handler::class ) )
            ->getAttributes( Handler::class );

        self::assertCount( 1, $attrs );

        /** @var Handler $handler */
        $handler = $attrs[0]->newInstance();
        self::assertSame( 'init', $handler->tag );
        self::assertSame( 10, $handler->priority );
    }

    public function test_filter_attribute_on_change_body_class(): void {
        $method = ( new ReflectionClass( Body_Handler::class ) )
            ->getMethod( 'change_body_class' );

        $attrs = $method->getAttributes( Filter::class );
        self::assertCount( 1, $attrs );

        /** @var Filter $filter */
        $filter = $attrs[0]->newInstance();
        self::assertSame( 'body_class', $filter->tag );
        self::assertSame( 10, $filter->priority );
    }
}
