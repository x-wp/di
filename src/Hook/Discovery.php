<?php //phpcs:disable Generic.Commenting.DocComment.MissingShort, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/**
 * Build definitions from unbound attribute declarations.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use DI\Definition\Exception\InvalidDefinition;
use ReflectionClass;
use ReflectionMethod;
use XWP\DI\Decorators as D;
use XWP\DI\Definition\CallbackDefinition;
use XWP\DI\Definition\HandlerDefinition;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Utils\Reflection;

/**
 * Separates constructor metadata from discovery binding.
 *
 * @internal Hook discovery detail.
 */
final class Discovery {
    // Inspect custom ancestry and methods together to identify the migration error precisely.
    // phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    /**
     * Reject obsolete runtime extensions before discovery or cached reconstruction.
     *
     * Constructors and declaration exporters remain valid metadata extension points.
     * Inspect each custom ancestor so inherited overrides cannot disappear silently.
     *
     * @param class-string $type Attribute class.
     * @return void
     * @throws InvalidDefinition If an attribute still overrides runtime behavior.
     */
    public static function assert_metadata( string $type ): void {
        $builtins  = array(
            D\Hook::class,
            D\Handler::class,
            D\Module::class,
            D\Ajax_Handler::class,
            D\REST_Handler::class,
            D\CLI_Handler::class,
            D\Filter::class,
            D\Action::class,
            D\Dynamic_Filter::class,
            D\Dynamic_Action::class,
            D\Ajax_Action::class,
            D\REST_Route::class,
            D\CLI_Command::class,
            D\Infuse::class,
        );
        $legacy    = array(
            '__get',
            '__invoke',
            'get',
            'resolve',
            'get_data',
            'get_tag',
            'get_modifiers',
            'get_priority',
            'get_container',
            'get_classname',
            'get_context',
            'get_init_hook',
            'get_token',
            'get_reflector',
            'is_cached',
            'is_loaded',
            'can_load',
            'check_context',
            'check_method',
            'can_call',
            'resolve_priority',
            'filter_priority',
            'call_priority',
            'get_token_base',
            'get_token_suffix',
            'get_app_uuid',
            'get_cb_arg',
            'generate_token',
            'on_initialize',
            'get_target',
            'get_params',
            'get_strategy',
            'get_callbacks',
            'get_lazy_tag',
            'get_compat_args',
            'is_lazy',
            'is_hookable',
            'lazy_load',
            'load',
            'instantiate',
            'initialize',
            'configure_async',
            'method_exists',
            'resolve_params',
            'check_initializer',
            'invoke',
            'get_type',
            'current',
            'init_handler',
            'cb_valid',
            'load_hook',
            'fire_hook',
            'get_cb_args',
            'handle_exception',
            'get_handler',
            'get_method',
            'get_num_args',
            'parse_vars',
            'process_vars',
            'get_prefix',
            'resolve_tag',
            'getter_cb',
            'fire_guard_cb',
            'nonce_check',
            'cap_check',
            'parse_nonce',
            'get_route',
            'get_guard',
            'get_callback',
            'get_vars',
            'get_methods',
            'get_before_invoke',
            'get_after_invoke',
            'get_command',
            'get_subcommand',
            'get_longdesc',
            'get_shortdesc',
            'run_cmd',
            'get_invoke',
            'get_hook_args',
            'parse_cmd_args',
            'get_arg_opts',
            'format_pos_args',
            'format_flag_args',
            'get_namespace',
            'get_basename',
            'get_rest_hook',
            'add_command',
            'get_imports',
            'get_handlers',
            'get_services',
            'get_configuration',
            'choice',
            'prompt',
            'track',
            'tick',
            'finish',
        );
        $reflector = new ReflectionClass( $type );
        while ( $reflector && ! \in_array( $reflector->getName(), $builtins, true ) ) {
            foreach ( $reflector->getMethods() as $method ) {
                if ( $method->getDeclaringClass()->getName() !== $reflector->getName() ) {
                    continue;
                }
                $name = \strtolower( $method->getName() );
                if ( \str_starts_with( $name, 'with_' ) || \in_array( $name, $legacy, true ) ) {
                    throw new InvalidDefinition(
                        "Attribute {$type} overrides legacy runtime method {$name}(). Migrate to constructor/get_declaration() metadata (Infuse: get_tokens()) or a Hook runtime extension.",
                    );
                }
            }
            $reflector = $reflector->getParentClass();
        }
    }

    // phpcs:enable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh

    /**
     * Validate serialized hook metadata before reconstructing definitions or runtimes.
     *
     * @param array{type:class-string,args:array<string,mixed>,params:array<string,mixed>} $data Hook metadata.
     * @return void
     */
    public static function assert_hook_metadata( array $data ): void {
        self::assert_metadata( $data['type'] );
        if ( ! \is_a( $data['type'], D\Handler::class, true ) ) {
            return;
        }

        self::assert_handler_metadata( $data['params']['classname'] );
    }

    /**
     * Validate declarations omitted from serialized handler metadata.
     *
     * Cached tokens remain authoritative; validation never evaluates exporters.
     *
     * @param class-string $classname Handler class.
     * @return void
     */
    public static function assert_handler_metadata( string $classname ): void {
        $reflector = Reflection::get_reflector( $classname );
        $handler   = Reflection::get_attribute( $reflector, D\Handler::class );
        if ( $handler ) {
            self::assert_metadata( $handler->getName() );
        }
        foreach ( $reflector->getMethods() as $method ) {
            foreach ( Reflection::get_attributes( $method, D\Infuse::class ) as $attribute ) {
                self::assert_metadata( $attribute->getName() );
            }
        }
    }

    /**
     * Read a handler declaration, retaining custom attribute overrides.
     *
     * @param class-string $classname Handler class.
     * @param class-string $type Attribute contract.
     * @return HandlerDefinition|null
     */
    public function handler( string $classname, string $type = D\Handler::class ): ?HandlerDefinition {
        $reflector   = Reflection::get_reflector( $classname );
        $declaration = Reflection::get_attribute( $reflector, $type );
        if ( ! $declaration ) {
            return null;
        }
        self::assert_metadata( $declaration->getName() );
        $attribute = $declaration->newInstance();

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
     * @return array<CallbackDefinition>
     */
    public function callbacks( HandlerDefinition|Can_Handle $handler, ReflectionMethod $method ): array {
        $callbacks = array();
        foreach ( Reflection::get_attributes( $method, D\Filter::class ) as $declaration ) {
            self::assert_metadata( $declaration->getName() );
            $callbacks[] = $this->callback_definition( $declaration->newInstance(), $handler, $method );
        }
        return $this->unique_callbacks( $callbacks );
    }

    // Keep ordinal assignment and metadata round trips together.
    // phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    /**
     * Disambiguate repeated declarations while preserving existing single IDs.
     *
     * @param array<CallbackDefinition> $callbacks Method declarations.
     * @return array<CallbackDefinition>
     */
    private function unique_callbacks( array $callbacks ): array {
        $seen = array();
        foreach ( $callbacks as $index => $callback ) {
            $token          = $callback->get_token();
            $occurrence     = $seen[ $token ] ?? 0;
            $seen[ $token ] = $occurrence + 1;
            if ( 0 === $occurrence ) {
                continue;
            }
            $token                  .= '#' . ( $occurrence + 1 );
            $data                    = $callback->get_data();
            $data['params']['token'] = $token;
            if ( isset( $data['args']['invoke'] ) ) {
                $data['args']['invoke'] = ( $callback->get_invoke() | D\Filter::INV_PROXIED ) & ~D\Filter::INV_STANDARD;
            }
            $callbacks[ $index ] = CallbackDefinition::from_data( $data );
        }
        return $callbacks;
    }
    // phpcs:enable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh

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
        $declaration = Reflection::get_attribute( $reflector->getMethod( 'on_initialize' ), D\Infuse::class );
        if ( ! $declaration ) {
            return $definition;
        }
        self::assert_metadata( $declaration->getName() );
        $infuse                                    = $declaration->newInstance();
        $data                                      = $definition->get_data();
        $data['params']['params']['on_initialize'] = $infuse->get_tokens( $definition->get_id() );
        return HandlerDefinition::from_data( $data );
    }

    // Argument inference, lazy invocation, and REST registration form one metadata conversion.
    // phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    /**
     * Combine constructor metadata with method and handler metadata.
     *
     * @param D\Filter<object,Can_Handle<object>>  $attribute Built-in declaration.
     * @param HandlerDefinition|Can_Handle<object> $handler Handler definition or runtime.
     * @param ReflectionMethod                     $method Callback method.
     * @return CallbackDefinition
     */
    private function callback_definition( D\Filter $attribute, HandlerDefinition|Can_Handle $handler, ReflectionMethod $method ): CallbackDefinition {
        $args   = $attribute->get_declaration();
        $params = array(
            'classname' => $handler instanceof HandlerDefinition ? $handler->get_class() : $handler->get_classname(),
            'method'    => $method->getName(),
        );
        if ( ! $attribute instanceof D\Ajax_Action && ! $attribute instanceof D\REST_Route && ! $attribute instanceof D\CLI_Command ) {
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
    }// phpcs:enable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
}
