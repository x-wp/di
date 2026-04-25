<?php
/**
 * ModuleDefinitionHelper class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Definition\Helper;

use DI\Definition\Helper\AutowireDefinitionHelper;
use XWP\DI\Definition\ModuleDefinition;

/**
 * PHP-DI helper for module definitions.
 */
class ModuleDefinitionHelper extends AutowireDefinitionHelper implements HookDefinition {
    /**
     * Imported module class names.
     *
     * @var array<int,class-string>
     */
    private array $imports = array();

    /**
     * Provided service class names.
     *
     * @var array<int,class-string>
     */
    private array $provides = array();

    /**
     * Exported service class names.
     *
     * @var array<int,class-string>
     */
    private array $exports = array();

    /**
     * Constructor.
     *
     * @param class-string $module Module class name.
     */
    public function __construct( string $module ) {
        parent::__construct( ModuleDefinition::class );

        $this->metatype( $module );
    }

    /**
     * Set the module class this definition describes.
     *
     * @param  class-string $metatype Module class name.
     * @return self
     */
    public function metatype( string $metatype ): self {
        return $this->constructorParameter( 0, $metatype );
    }

    /**
     * Set imported modules.
     *
     * @param  class-string ...$modules Imported module class names.
     * @return self
     */
    public function imports( string ...$modules ): self {
        $this->imports = \array_values( $modules );

        return $this->constructorParameter( 1, $this->imports );
    }

    /**
     * Set provided services.
     *
     * @param  class-string ...$services Provided service class names.
     * @return self
     */
    public function provides( string ...$services ): self {
        $this->provides = \array_values( $services );

        return $this->constructorParameter( 2, $this->provides );
    }

    /**
     * Set exported services.
     *
     * @param  class-string ...$services Exported service class names.
     * @return self
     */
    public function exports( string ...$services ): self {
        $this->exports = \array_values( $services );

        return $this->constructorParameter( 3, $this->exports );
    }
}
