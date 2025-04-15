<?php //phpcs:disable WordPress.NamingConventions

namespace XWP\DI\Definition\Helper;

use Closure;
use DI\Definition\Definition;
use DI\Definition\Helper\AutowireDefinitionHelper;
use DI\Definition\Helper\DefinitionHelper;
use XWP\DI\Container;
use XWP\DI\Core\Wrapper;

/**
 * Helper to create a hook definition.
 */
class Hook_Definition_Helper extends AutowireDefinitionHelper {
    /**
     * Properties to hydrate.
     *
     * @var array<string>
     */
    private static array $hydrate = array( 'imports', 'callbacks', 'handlers' );

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
     * @param  array<string,mixed> $construct   Arguments to pass to the method.
     * @param  bool                $hydrate Whether to hydrate the object.
     * @return array<string,mixed>
     */
    protected function fix_constructor_args( array $construct, bool $hydrate ): array {
        $construct['container'] = \DI\get( Container::class );

        if ( $hydrate ) {
            $construct = $this->resolve_hydration( $construct );
        }

        if ( \array_key_exists( 'priority', $construct ) ) {
            $construct['priority'] = $this->resolve_priority( $construct['priority'], $construct['classname'] );
        }

        unset( $construct['id'] );
        $construct['debug'] ??= false;
        $construct['trace'] ??= false;

        return array( 'args' => $construct );
    }

    /**
     * Resolve the priority.
     *
     * @param  null|callable-string|numeric-string|string|int|array{0:class-string,1: string} $prio Priority.
     * @param  class-string                                                                   $classname Hook class name.
     * @return null|int|DefinitionHelper
     */
    protected function resolve_priority( null|callable|string|int|array $prio, string $classname ): null|int|DefinitionHelper {
        if ( \is_int( $prio ) ) {
            return $prio;
        }

        return match ( true ) {
            \is_null( $prio )     => \DI\value( null ),
            \is_callable( $prio ) => \DI\factory( $prio )->parameter( '0', 10 )->parameter( '1', $classname ),
            \defined( $prio )     => \DI\factory( 'constant' )->parameter( 'name', $prio ),
            \is_string( $prio )   => \XWP\DI\filter( $prio, 10, $classname )->cast( 'intval' ),
            default               => 10,
        };
    }

    /**
     * Resolve the arguments to hydrate.
     *
     * @param  array<string,mixed> $args Arguments to pass to the method.
     * @return array<string,mixed>
     */
    protected function resolve_hydration( array $args ): array {
        foreach ( self::$hydrate as $key ) {
            if ( ! isset( $args[ $key ] ) ) {
                continue;
            }

            $args[ $key ]       = $this->hydrate( $args[ $key ] );
            $args['hydrated'] ??= true;
        }

        return $args;
    }

    /**
     * Hydrate the arguments.
     *
     * @param  string|array<string,string> $value Arguments to pass to the method.
     * @return Definition|array<string,Definition>
     */
    protected function hydrate( array|string $value ): array|Definition {
        return \is_array( $value )
            ? \array_map( '\DI\get', $value )
            : \DI\get( $value );
    }
}
