<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found, Squiz.Commenting.FunctionComment.Missing
/**
 * Handler runtime class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use Closure;
use ReflectionClass;
use Reflector;
use XWP\DI\Compatibility\Hook;
use XWP\DI\Container;
use XWP\DI\Decorators\Infuse;
use XWP\DI\Definition\HandlerDefinition;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Utils\Reflection;

/**
 * Owns initialization and callback-discovery state for a handler definition.
 *
 * @internal
 *
 * @template T of object
 *
 * @extends Hook<T,ReflectionClass<T>>
 * @implements Can_Handle<T>
 */
class Handler extends Hook implements Can_Handle {
    /**
     * Shared initialization lifecycle and internal wiring adapters.
     *
     * @use \XWP\DI\Compatibility\Handler_Methods<T>
     */
    use \XWP\DI\Compatibility\Handler_Methods;

    /**
     * Constructor.
     *
     * @param HandlerDefinition $definition Handler metadata.
     * @param Container         $container Runtime container.
     */
    public function __construct( protected HandlerDefinition $definition, Container $container ) {
        parent::__construct(
            $definition->get_tag(),
            $definition->get_priority(),
            $definition->get_context(),
            $definition->get_conditional(),
            $definition->get_modifiers(),
        );
        $this->classname = $definition->get_class();
        $this->container = $container;
        $this->strategy  = $definition->get_strategy();
        $this->hookable  = $definition->is_hookable();
        $this->callbacks = $definition->get_callbacks();
        $this->with_params( $definition->get_params() );
    }

    public function get_definition(): HandlerDefinition {
        return $this->definition;
    }

    /**
     * Get compiler data for this handler.
     *
     * @internal Hook parser/compiler detail.
     *
     * @return array{
     *   args: array<string,mixed>,
     *   type: class-string,
     *   params: array{classname: class-string<T>},
     * }
     */
    public function get_data(): array {
        $args             = $this->definition->get_options();
        $args['priority'] = $this->definition->get_priority() ?? 10;
        if ( \XWP\DI\Decorators\Handler::class === $this->definition->get_decorator() ) {
            $args = \array_replace(
                $args,
                array(
                    'conditional' => $this->conditional,
                    'context'     => $this->context,
                    'hookable'    => $this->hookable,
                    'modifiers'   => $this->modifiers,
                    'strategy'    => $this->strategy,
                    'tag'         => $this->tag,
                ),
            );
        } elseif ( $this instanceof \XWP\DI\Interfaces\Can_Import ) {
            $args['hook']     = $this->tag;
            $args['context']  = $this->context;
            $args['strategy'] = $this->strategy;
        }

        return array(
            'args'   => $args,
            'params' => array(
                'callbacks' => $this->get_callbacks(),
                'classname' => $this->get_classname(),
                'params'    => \array_combine(
                    \array_keys( $this->params ),
                    \array_map(
                        fn( string $method ) => $this->get_params( $method )?->get( $this ) ?? array(),
                        \array_keys( $this->params ),
                    ),
                ),
            ),
            'type'   => $this->definition->get_decorator(),
        );
    }
}
