<?php
/**
 * ModuleDefinitionHelper unit tests.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Unit\Definition;

use DI\Definition\ObjectDefinition;
use Tests\XWP\DI\Unit\TestCase;
use XWP\DI\Definition\Helper\ModuleDefinitionHelper;
use XWP\DI\Definition\ModuleDefinition;

use function XWP\DI\module;

final class ModuleDefinitionHelper_Test extends TestCase {
    public function test_module_factory_returns_module_definition_helper(): void {
        self::assertInstanceOf( ModuleDefinitionHelper::class, module( Fixture_Module::class ) );
    }

    public function test_get_definition_wires_module_metatype(): void {
        $definition = module( Fixture_Module::class )->getDefinition( 'test.module' );

        self::assertInstanceOf( ObjectDefinition::class, $definition );
        self::assertSame( 'test.module', $definition->getName() );
        self::assertSame( ModuleDefinition::class, $definition->getClassName() );
        self::assertSame(
            Fixture_Module::class,
            $definition->getConstructorInjection()?->getParameters()[0] ?? null,
        );
    }

    public function test_helper_wires_imports_providers_and_exports(): void {
        $definition = module( Fixture_Module::class )
            ->imports( Imported_Module::class )
            ->provides( Provided_Service::class )
            ->exports( Provided_Service::class )
            ->getDefinition( 'test.module' );

        self::assertSame(
            array(
                0 => Fixture_Module::class,
                1 => array( Imported_Module::class ),
                2 => array( Provided_Service::class ),
                3 => array( Provided_Service::class ),
            ),
            $definition->getConstructorInjection()?->getParameters(),
        );
    }
}

final class Fixture_Module {
}

final class Imported_Module {
}

final class Provided_Service {
}
