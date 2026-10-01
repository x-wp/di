<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Module decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

/**
 * Module decorator.
 *
 * @template T of object
 * @extends Handler<T>
 *
 * @since 1.0.0
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Module extends Handler {
    /**
     * Constructor.
     *
     * @param string                  $hook       Hook name.
     * @param int                     $priority   Hook priority.
     * @param int                     $context    Module context.
     * @param array<int,class-string> $imports    Array of submodules to import.
     * @param array<int,class-string> $handlers   Array of handlers to register.
     * @param array<int,class-string> $services   Array of autowired services.
     * @param mixed                   ...$args    Deprecated arguments.
     */
    public function __construct(
        string $hook,
        int $priority = 10,
        int $context = self::CTX_GLOBAL,
        /**
         * Array of submodules to import.
         *
         * @var array<int,class-string>
         */
        protected array $imports = array(),
        /**
         * Array of handlers to register.
         *
         * @var array<int,class-string>
         */
        protected array $handlers = array(),
        /**
         * Array of autowired services.
         *
         * @var array<int,class-string>
         */
        protected array $services = array(),
        mixed ...$args,
    ) {
        $params = array(
            'args'     => $args,
            'context'  => $context,
            'priority' => $priority,
            'strategy' => $args['strategy'] ?? self::INIT_AUTO,
            'tag'      => $hook,
        );

        parent::__construct( ...$params );
    }

    /**
     * Get declared imports.
     *
     * @return array<int,class-string>
     */
    public function get_imports(): array {
        return $this->imports;
    }

    /**
     * Get declared handlers.
     *
     * @return array<int,class-string>
     */
    public function get_handlers(): array {
        return $this->handlers;
    }

    /**
     * Get declared services.
     *
     * @return array<int,class-string>
     */
    public function get_services(): array {
        return $this->services;
    }

    /**
     * Export unbound attribute metadata.
     *
     * @internal Discovery metadata detail.
     * @return array<string,mixed>
     */
    public function get_declaration(): array {
        $args = parent::get_declaration();
        foreach ( array( 'conditional', 'hookable', 'modifiers', 'strategy', 'tag' ) as $key ) {
            unset( $args[ $key ] );
        }
        $args += array(
            'handlers' => $this->handlers,
            'hook'     => $this->tag,
            'imports'  => $this->imports,
            'services' => $this->services,
        );
        if ( self::INIT_AUTO !== $this->strategy ) {
            $args['strategy'] = $this->strategy;
        }
        return $args;
    }
}
