<?php //phpcs:disable Generic.Commenting.DocComment.MissingShort
/**
 * Build definitions from unbound attribute declarations.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use ReflectionClass;
use ReflectionMethod;
use XWP\DI\Decorators as D;
use XWP\DI\Definition\CallbackDefinition;
use XWP\DI\Definition\HandlerDefinition;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;
use XWP\DI\Utils\Reflection;

/**
 * Separates constructor metadata from discovery binding.
 *
 * @internal Hook discovery detail.
 */
final class Discovery {
    /**
     * Shared adapters for explicitly supplied definitions with custom callbacks.
     *
     * @var \WeakMap<HandlerDefinition,Can_Handle<object>>
     */
    private \WeakMap $legacy_handlers;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->legacy_handlers = new \WeakMap();
    }

    /**
     * Read a handler declaration, retaining custom attribute overrides.
     *
     * @param class-string $classname Handler class.
     * @param class-string $type Attribute contract.
     * @return HandlerDefinition|Can_Handle<object>|null
     */
    public function handler( string $classname, string $type = Can_Handle::class ): HandlerDefinition|Can_Handle|null {
        $reflector = new ReflectionClass( $classname );
        $attribute = Reflection::get_decorator( $reflector, $type );
        if ( ! $attribute ) {
            return null;
        }
        $builtins = array( D\Handler::class, D\Module::class, D\Ajax_Handler::class, D\REST_Handler::class, D\CLI_Handler::class );
        $builtin  = \in_array( $attribute::class, $builtins, true );
        if ( ! $builtin || $this->has_custom_declarations( $reflector ) ) {
            return $attribute->with_reflector( $reflector );
        }

        $params              = array( 'classname' => $reflector->getName() );
        $params['callbacks'] = null;
        $params['params']    = array( 'on_initialize' => array() );
        $definition          = HandlerDefinition::from_data(
            array(
                'args'   => $attribute->get_declaration(),
                'params' => $params,
                'type'   => $attribute::class,
            ),
        );
        return $this->initializer( $definition, $reflector );
    }

    /**
     * Read method declarations without wiring built-in decorators.
     *
     * @param HandlerDefinition|Can_Handle<object> $handler Handler metadata or runtime.
     * @param ReflectionMethod                     $method Reflected callback method.
     * @return array<CallbackDefinition|Can_Invoke<object,Can_Handle<object>>>
     */
    public function callbacks( HandlerDefinition|Can_Handle $handler, ReflectionMethod $method ): array {
        $callbacks = array();
        $builtins  = array( D\Filter::class, D\Action::class, D\Dynamic_Filter::class, D\Dynamic_Action::class, D\Ajax_Action::class, D\REST_Route::class, D\CLI_Command::class );
        foreach ( Reflection::get_decorators( $method, Can_Invoke::class ) as $attribute ) {
            if ( ! \in_array( $attribute::class, $builtins, true ) ) {
                $legacy      = $this->legacy_handler( $handler );
                $callbacks[] = $attribute->with_handler( $legacy )->with_reflector( $method );
                continue;
            }

            $callbacks[] = $this->callback_definition( $attribute, $handler, $method );
        }
        return $callbacks;
    }

    /**
     * Keep mutable extension discovery on its original handler object.
     *
     * @param ReflectionClass<object> $reflector Handler reflection.
     * @return bool
     */
    private function has_custom_declarations( ReflectionClass $reflector ): bool {
        foreach ( $reflector->getMethods() as $method ) {
            if ( $this->has_custom_method_declaration( $method ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check extension types without constructing their attributes early.
     *
     * @param ReflectionMethod $method Handler method.
     * @return bool
     */
    private function has_custom_method_declaration( ReflectionMethod $method ): bool {
        $builtins   = array( D\Filter::class, D\Action::class, D\Dynamic_Filter::class, D\Dynamic_Action::class, D\Ajax_Action::class, D\REST_Route::class, D\CLI_Command::class, D\Infuse::class );
        $attributes = \array_merge(
            $method->getAttributes( Can_Invoke::class, \ReflectionAttribute::IS_INSTANCEOF ),
            $method->getAttributes( D\Infuse::class, \ReflectionAttribute::IS_INSTANCEOF ),
        );
        foreach ( $attributes as $attribute ) {
            if ( ! \in_array( $attribute->getName(), $builtins, true ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Capture initializer injection tokens without binding a built-in handler.
     *
     * @param HandlerDefinition       $definition Handler definition.
     * @param ReflectionClass<object> $reflector Handler reflection.
     * @return HandlerDefinition
     */
    private function initializer( HandlerDefinition $definition, ReflectionClass $reflector ): HandlerDefinition {
        if ( ! $reflector->hasMethod( 'on_initialize' ) ) {
            return $definition;
        }
        $infuse = Reflection::get_decorator( $reflector->getMethod( 'on_initialize' ), D\Infuse::class );
        if ( ! $infuse ) {
            return $definition;
        }
        $data                                      = $definition->get_data();
        $data['params']['params']['on_initialize'] = D\Infuse::class === $infuse::class
            ? $infuse->get_tokens( $definition->get_id() )
            : $infuse->get( $this->legacy_handler( $definition ) );
        return HandlerDefinition::from_data( $data );
    }

    // Argument inference, lazy invocation, and REST registration form one metadata conversion.
    // phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    /**
     * Combine constructor metadata with method and handler metadata.
     *
     * @param D\Hook<object,ReflectionMethod>      $attribute Built-in declaration.
     * @param HandlerDefinition|Can_Handle<object> $handler Handler definition or runtime.
     * @param ReflectionMethod                     $method Callback method.
     * @return CallbackDefinition
     */
    private function callback_definition( D\Hook $attribute, HandlerDefinition|Can_Handle $handler, ReflectionMethod $method ): CallbackDefinition {
        $args     = $attribute->get_declaration();
        $params   = array(
            'classname' => $handler instanceof HandlerDefinition ? $handler->get_class() : $handler->get_classname(),
            'method'    => $method->getName(),
        );
        $inferred = array( D\Filter::class, D\Action::class, D\Dynamic_Filter::class, D\Dynamic_Action::class );
        if ( \in_array( $attribute::class, $inferred, true ) ) {
            $dynamic        = $attribute instanceof D\Dynamic_Filter;
            $args['args'] ??= $method->getNumberOfParameters() - (int) $dynamic;
        }
        $lazy = $handler instanceof HandlerDefinition
            ? \in_array( $handler->get_strategy(), array( D\Handler::INIT_LAZY, D\Handler::INIT_JIT ), true )
            : $handler->is_lazy();
        if ( isset( $args['invoke'] ) && $lazy ) {
            $args['invoke'] = ( $args['invoke'] | D\Filter::INV_PROXIED ) & ~D\Filter::INV_STANDARD;
        }
        if ( $attribute instanceof D\REST_Route ) {
            /** @var \XWP\DI\Interfaces\Can_Handle_REST<\XWP_REST_Controller>|HandlerDefinition $handler */
            $params['tag'] = $handler instanceof HandlerDefinition
                ? $handler->get_options()['namespace'] . '/' . $handler->get_options()['basename']
                : $handler->get_rest_hook();
            /** @var int $priority */
            $priority           = $handler->get_priority();
            $params['priority'] = $priority + 1;
        }
        return CallbackDefinition::from_data(
            array(
                'args'   => $args,
                'params' => $params,
                'type'   => $attribute::class,
            ),
        );
    }
    // phpcs:enable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh

    /**
     * Adapt a built-in definition only when a custom attribute needs the old contract.
     *
     * @param HandlerDefinition|Can_Handle<object> $definition Discovered handler.
     * @return Can_Handle<object>
     */
    private function legacy_handler( HandlerDefinition|Can_Handle $definition ): Can_Handle {
        if ( $definition instanceof Can_Handle ) {
            return $definition;
        }
        if ( isset( $this->legacy_handlers[ $definition ] ) ) {
            return $this->legacy_handlers[ $definition ]->with_reflector(
                new ReflectionClass( $definition->get_class() ),
            );
        }
        $data      = $definition->get_data();
        $classname = $definition->get_decorator();

        $this->legacy_handlers[ $definition ] = ( new $classname( ...$data['args'] ) )->with_data(
            $data['params'],
        );
        return $this->legacy_handlers[ $definition ];
    }
}
