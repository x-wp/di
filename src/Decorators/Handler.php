<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found, Squiz.Commenting.FunctionComment.Missing
/**
 * Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use Reflector;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Decorates_Callback;
use XWP\DI\Interfaces\Decorates_Handler;
use XWP\DI\Utils\Reflection;

/**
 * Decorator for handling WordPress hooks.
 *
 * @template T of object
 *
 * @extends Hook<T,ReflectionClass<T>>
 * @implements Decorates_Handler<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Handler extends Hook implements Decorates_Handler {
    /**
     * The initialization strategy.
     *
     * @var string
     */
    protected string $strategy;

    /**
     * Are we passing arguments to the action.
     *
     * @var int
     */
    protected int $delegate;

    /**
     * Array of hooked methods
     *
     * @var ?array<int,Decorates_Callback<T>>
     */
    protected ?array $callbacks = null;

    /**
     * Hook arguments.
     *
     * @var array<int,string|null>
     */
    protected array $hook_args;

    /**
     * Create a hook for a given class.
     *
     * @param  class-string<T> $classname Classname of the handler.
     * @return static<T>|null
     */
    public static function from_classname( string $classname ): ?static {
        /**
         * Reflection class for the hook.
         *
         * @var ReflectionClass<T>
         */
        $reflector = Reflection::get_reflector( $classname );

        // @phpstan-ignore return.type
        return Reflection::get_decorator( $reflector, static::class )?->with_reflector( $reflector );
    }

    /**
     * Constructor.
     *
     * @param ?string                                                $tag         Hook tag.
     * @param null|Closure|string|int|array{0:class-string,1:string} $priority    Hook priority.
     * @param int                                                    $context     Hook context.
     * @param array<int,string>|string|false                         $modifiers   Values to replace in the tag name.
     * @param string                                                 $strategy    Initialization strategy.
     * @param int<0,2>                                               $delegate    Pass arguments to the action.
     * @param array<int,string>|int                                  $hook_args Number of arguments to pass to the action.
     * @param bool                                                   $debug       Debug this hook.
     * @param bool                                                   $trace       Trace this hook.
     */
    public function __construct(
        ?string $tag = null,
        null|Closure|string|int|array $priority = 10,
        int $context = self::CTX_GLOBAL,
        string|array|false $modifiers = false,
        string $strategy = self::INIT_AUTO,
        int $delegate = self::DELEGATE_NEVER,
        int|array $hook_args = array(),
        bool $debug = false,
        bool $trace = false,
    ) {
        parent::__construct(
            tag: $tag,
            priority: $tag ? $priority ?? 10 : null,
            context: $context,
            modifiers: $modifiers,
            debug: $debug,
            trace: $trace,
        );

        $this->delegate  = $delegate;
        $this->hook_args = ! \is_array( $hook_args ) ? \array_fill( 0, $hook_args, null ) : $hook_args;
        $this->strategy  = $strategy;
    }

    public function get_callbacks(): array {
        if ( isset( $this->callbacks ) ) {
            return $this->callbacks;
        }

        $callbacks = array();

        foreach ( $this->get_methods() as $method ) {
            $callbacks[] = $this->get_method_callbacks( $method );
        }

        return $this->callbacks ??= \array_merge( ...$callbacks );
    }

    public function get_target(): ?object {
        return $this->instance ?? null;
    }

    public function get_hook_args_count(): int {
        if ( ! $this->hook_args ) {
            return 0;
        }

        return \max( \count( $this->hook_args ), ...\array_keys( $this->hook_args ) );
    }

    /**
     * Get the reflector instance.
     *
     * @return ReflectionClass<T>
     */
    public function get_reflector(): ReflectionClass {
        return $this->reflector ??= new ReflectionClass( $this->classname );
    }

    public function get_data(): array {
        $data = parent::get_data();

        $data['construct']['callbacks'] = \array_map(
            static fn( $cb ) => $cb->get_token(),
            $this->get_callbacks(),
        );

        return $data;
    }

    public function with_reflector( Reflector $r ): static {
        $this->classname = $r->getName();

        return parent::with_reflector( $r );
    }

    protected function get_constructor_args(): array {
        return \array_merge(
            parent::get_constructor_args(),
            array( 'hookable', 'strategy' ),
        );
    }

    protected function get_token_prefix(): string {
        return 'h:';
    }

    /**
     * Get the hookable methods
     *
     * @return array<ReflectionMethod>
     */
    private function get_methods(): array {
        return Reflection::get_hookable_methods( $this->get_reflector() );
    }

    /**
     * Get the method callbacks.
     *
     * @param  ReflectionMethod $m The method to get the callbacks for.
     * @return array<int,Decorates_Callback<T>>
     */
    private function get_method_callbacks( ReflectionMethod $m ): array {
        $callbacks = array();

        foreach ( Reflection::get_decorators( $m, Decorates_Callback::class ) as $cb ) {
            $callbacks[] = $cb->with_reflector( $m )->with_handler( $this );
        }

        return $callbacks;
    }
}
