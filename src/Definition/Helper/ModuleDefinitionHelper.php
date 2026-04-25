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
     * Handler class names.
     *
     * @var array<int,class-string>
     */
    private array $handlers = array();

    /**
     * Autowired service class names.
     *
     * @var array<int,class-string>
     */
    private array $services = array();

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
     * Set handlers.
     *
     * @param  class-string ...$classes Handler class names.
     * @return self
     */
    public function handlers( string ...$classes ): self {
        $this->handlers = \array_values( $classes );

        return $this->constructorParameter( 2, $this->handlers );
    }

    /**
     * Set autowired services.
     *
     * @param  class-string ...$classes Autowired service class names.
     * @return self
     */
    public function services( string ...$classes ): self {
        $this->services = \array_values( $classes );

        return $this->constructorParameter( 3, $this->services );
    }
}
