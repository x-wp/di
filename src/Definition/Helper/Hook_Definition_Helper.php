<?php //phpcs:disable WordPress.NamingConventions

namespace XWP\DI\Definition\Helper;

use Closure;
use DI\Definition\Helper\AutowireDefinitionHelper;
use DI\Definition\Helper\DefinitionHelper;
use XWP\DI\Container;
use XWP\DI\Core\Wrapper;

/**
 * Helper to create a hook definition.
 */
class Hook_Definition_Helper extends AutowireDefinitionHelper {
    private static array $hydrate = array( 'imports', 'callbacks', 'handlers', 'handler' );
    /**
     * Hook data.
     *
     * @var array<string,mixed>
     */
    protected array $data = array();

    /**
     * Constructor.
     *
     * @param class-string        $metatype Class name of the hook.
     * @param array<string,mixed> $construct Arguments to pass to the method.
     * @param bool                $hydrate Whether to hydrate the object.
     */
    public function __construct( string $metatype, array $construct, bool $hydrate ) {
        parent::__construct( $metatype );

        $this->constructor = $this->fix_constructor_args( $construct, $hydrate );
    }

    /**
     * Fix the constructor arguments.
     *
     * @param  array<string,mixed> $args   Arguments to pass to the method.
     * @param  bool                $hydrate Whether to hydrate the object.
     * @return array<string,mixed>
     */
    protected function fix_constructor_args( array $args, bool $hydrate ): array {
        $args['container'] = \DI\get( Container::class );

        if ( $hydrate ) {
            foreach ( self::$hydrate as $key ) {
                if ( ! isset( $args[ $key ] ) ) {
                    continue;
                }

                $args[ $key ] = $this->hydrate( $args[ $key ] );
            }

            $args['hydrated'] = true;
        }

        if ( \array_key_exists( 'priority', $args ) ) {
            $args['priority'] = $this->resolve_priority( $args['priority'], $args['classname'] );
        }

        unset( $args['id'] );

        return $args;
    }

    /**
     * Resolve the priority.
     *
     * @param  null|Closure|string|int|array{0:class-string,1: string} $prio Priority.
     * @param  class-string                                            $classname Hook class name.
     * @return int|DefinitionHelper
     */
    protected function resolve_priority( null|Closure|string|int|array $prio, string $classname ): int|DefinitionHelper {
        return match ( true ) {
            \is_callable( $prio ),
            \is_array( $prio )   => \DI\factory( $prio )->parameter( '0', 10 )->parameter( '1', $classname ),
            \defined( $prio )    => \constant( $prio ),
            \is_numeric( $prio ) => (int) $prio,
            \is_string( $prio )  => \XWP\DI\filter( $prio, 10, $classname )->cast( 'intval' ),
            default              => 10,
        };
    }

    protected function hydrate( array|string $value ): array|string {
        return \is_array( $value )
            ? \array_map( '\DI\get', $value )
            : \DI\get( $value );
    }
}
