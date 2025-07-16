<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
/**
 * Hook class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Automattic\Jetpack\Constants;
use Closure;
use ReflectionClass;
use ReflectionMethod;
use XWP\DI\Container;
use XWP\DI\Interfaces\Can_Hook;
use XWP\DI\Traits\Hook_Invoke_Methods;
use XWP_Context;

/**
 * Base hook from which the action and filter decorators inherit.
 *
 * @template THndlr of object
 * @template TRflct of ReflectionClass<THndlr>|ReflectionMethod
 * @implements Can_Hook<THndlr,TRflct>
 */
abstract class Hook implements Can_Hook {
    /**
     * Define the shared methods needed for hook invocation.
     *
     * @use Hook_Invoke_Methods<THndlr>
     */
    use Hook_Invoke_Methods;

    /**
     * Is the hook definition cached?
     *
     * @var bool
     */
    protected bool $cached = false;

    /**
     * The name of the action to which the function is hooked.
     *
     * @var string
     */
    protected string $tag;

    /**
     * Priority when hook was invoked.
     *
     * @var null|Closure|string|int|array{0: class-string,1: string}
     */
    protected null|Closure|string|int|array $prio;

    /**
     * The classname of the handler.
     *
     * @var class-string<THndlr>
     */
    protected string $classname;

    /**
     * The container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Is the handler initialized?
     *
     * @var bool
     */
    protected bool $loaded = false;

    /**
     * Reflector instance.
     *
     * @var TRflct
     */
    protected ReflectionClass|ReflectionMethod $reflector;

    /**
     * Initialization hook.
     *
     * @var string
     */
    protected string $init_hook;

    /**
     * Injection token.
     *
     * @var string
     */
    private string $token;

    /**
     * Constructor.
     *
     * @param string|null                                             $tag         Hook tag.
     * @param null|Closure|string|int|array{0:class-string,1: string} $priority    Hook priority.
     * @param int                                                     $context     Hook context.
     * @param null|Closure|string|array{0:class-string,1: string}     $conditional Conditional callback.
     * @param array<int,string>|string|false                          $modifiers   Values to replace in the tag name.
     * @param bool                                                    $debug       Debug this hook.
     * @param bool                                                    $trace       Trace this hook.
     */
    public function __construct(
        ?string $tag,
        array|int|string|Closure|null $priority = null,
        protected int $context = self::CTX_GLOBAL,
        protected array|string|Closure|null $conditional = null,
        protected string|array|bool $modifiers = false,
        protected bool $debug = false,
        protected bool $trace = false,
    ) {
        $this->prio = $priority;
        $this->tag  = $tag ?? '';
    }

    /**
     * Getter for protected properties.
     *
     * @param  string $name Property name.
     * @return mixed
     */
    public function __get( string $name ): mixed {
        return \method_exists( $this, "get_{$name}" )
            ? $this->{"get_{$name}"}()
            : $this->$name ?? null;
    }

    public function with_cache( bool $cached ): static {
        $this->cached = $cached;

        return $this;
    }

    public function with_classname( string $classname ): static {
        $this->classname = $classname;

        return $this;
    }

    public function with_container( null|string|Container $container ): static {
        if ( $container instanceof Container ) {
            $this->container = $container;
        }

        return $this;
    }

    /**
     * Set the reflector instance.
     *
     * @param  TRflct $r Reflector instance.
     * @return static
     */
    public function with_reflector( \Reflector $r ): static {
        $this->reflector ??= $r;

        return $this;
    }

    public function with_data( array $data ): static {
        foreach ( $data as $arg => $value ) {
            $this->{"with_{$arg}"}( $value );
        }

        return $this;
    }

    public function get_tag(): string {
        return $this->resolve_tag( $this->tag, $this->get_modifiers() );
    }

    public function get_modifiers(): array|string|bool {
        return $this->modifiers
            ? \array_map( array( $this, 'get_cb_arg' ), (array) $this->modifiers )
            : $this->modifiers;
    }

    public function get_priority(): int {
        return $this->resolve_priority( $this->prio );
    }

    public function get_container(): ?Container {
        return $this->container ?? null;
    }

    public function get_classname(): string {
        return $this->classname;
    }

    public function get_data(): array {
        return array(
            'args'   => array(
                'conditional' => $this->conditional,
                'context'     => $this->context,
                'modifiers'   => $this->modifiers,
                'priority'    => $this->prio,
                'tag'         => $this->tag,
            ),
            'params' => array(
                'classname' => $this->classname,
            ),
            'type'   => static::class,
        );
    }

    public function get_context(): int {
        return $this->context;
    }

    public function get_init_hook(): string {
        return $this->init_hook;
    }

    final public function get_token(): string {
        return $this->token ??= $this->generate_token();
    }

    public function is_cached(): bool {
        return $this->cached;
    }

    public function is_loaded(): bool {
        return $this->loaded;
    }

    /**
     * Check if the hook can be fired.
     *
     * @return bool
     */
    public function can_load(): bool {
        return $this->check_context() && $this->check_method( $this->conditional );
    }

    /**
     * Check if the context is valid.
     *
     * @return bool
     */
    public function check_context(): bool {
        return XWP_Context::validate( $this->get_context() );
    }

    /**
     * Get the token base.
     *
     * @return string
     */
    protected function get_token_base(): string {
        return $this->get_classname();
    }

    /**
     * Get the token suffix.
     *
     * @return string
     */
    protected function get_token_suffix(): string {
        return '';
    }

    protected function get_app_uuid(): string {
        return $this->get_container()->get( 'app.uuid' );
    }

    protected function get_cb_arg( string $param ): mixed {
        return match ( true ) {
            '!self.hook' === $param                => $this,
            \str_starts_with( $param, '!value:' )  => \str_replace( '!value:', '', $param ),
            \str_starts_with( $param, '!global:' ) => $GLOBALS[ \str_replace( '!global:', '', $param ) ] ?? null,
            \str_starts_with( $param, '!const:' )  => Constants::get_constant( \str_replace( '!const:', '', $param ) ),
            $this->container->has( $param )        => $this->container->get( $param ),
            default                                => $param,
        };
    }

    /**
     * Generate the injection token.
     *
     * @return string
     */
    private function generate_token(): string {
        $suffix = \ltrim( $this->get_token_suffix(), '-' );
        $base   = \trim( $this->get_token_base(), '-' );

        return \trim( "Hook-{$base}::{$suffix}", '-:/' );
    }
}
