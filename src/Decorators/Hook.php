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
use Psr\Log\LoggerInterface;
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
     * Is the hook enabled?
     *
     * @var bool
     */
    protected bool $enabled = true;

    /**
     * Is the hook loaded?
     *
     * @var bool
     */
    protected bool $loaded = false;

    /**
     * Is the hook ready.
     *
     * @var bool
     */
    protected bool $ready = false;

    /**
     * Is the hook definition cached?
     *
     * @var bool
     */
    protected bool $cached = false;

    /**
     * Define the shared methods needed for hook invocation.
     *
     * @use Hook_Invoke_Methods<THndlr>
     */
    use Hook_Invoke_Methods;

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
    protected null|Closure|string|int|array $priority;

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
     * Is the handler unloaded
     *
     * @var bool
     */
    protected bool $unloaded = false;

    /**
     * The reason the handler was unloaded.
     *
     * @var string
     */
    protected string $reason = '';

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
     * Logger instance.
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Injection token.
     *
     * @var string
     */
    private string $token;

    /**
     * Deprecated constructor arguments.
     *
     * @var array<string>
     */
    protected array $compat_args = array();

    /**
     * Constructor.
     *
     * @param string|null                                             $tag         Hook tag.
     * @param null|Closure|string|int|array{0:class-string,1: string} $priority    Hook priority.
     * @param int                                                     $context     Hook context.
     * @param array<int,string>|string|false                          $modifiers   Values to replace in the tag name.
     * @param bool                                                    $debug       Debug this hook.
     * @param bool                                                    $trace       Trace this hook.
     */
    public function __construct(
        ?string $tag,
        array|int|string|Closure|null $priority = null,
        protected int $context = self::CTX_GLOBAL,
        protected string|array|bool $modifiers = false,
        protected bool $debug = false,
        protected bool $trace = false,
    ) {
        $this->priority = $priority;
        $this->tag      = $tag ?? '';
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

    public function with_trace( bool $trace ): static {
        $this->trace = $trace;

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
        return $this->resolve_priority( $this->priority );
    }

    public function get_container(): ?Container {
        return $this->container ?? null;
    }

    public function get_classname(): string {
        return $this->classname;
    }

    public function get_shortname(): string {
        $name = \explode( '\\', $this->get_classname() );

        return \end( $name );
    }

    public function get_data(): array {
        return array(
            'args'   => \array_combine(
                $this->get_constructor_args(),
                \array_map(
                    fn( string $arg ) => $this->$arg,
                    $this->get_constructor_args(),
                ),
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

    public function get_logger(): LoggerInterface {
        return $this->logger ??= $this->get_container()->logger( $this->get_classname() );
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

    public function is_enabled(): bool {
        return $this->enabled;
    }

    public function is_ready(): bool {
        return $this->ready;
    }

    public function debug(): bool {
        return $this->debug;
    }

    public function trace(): bool {
        return $this->trace;
    }

    /**
     * Check if the hook can be fired.
     *
     * @return bool
     */
    public function can_load(): bool {
        return $this->is_enabled();
    }

    public function disable( string $reason = '' ): static {
        if ( ! $this->is_enabled() ) {
            return $this;
        }

        $this->enabled = false;

        if ( $this->trace() ) {
            $this->get_logger()->info( \sprintf( 'Hook disabled. Reason: %s', $reason ) );
        }

        return $this;
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
     * Merge the compatibility arguments.
     *
     * @param  array<string,mixed> $data Data to merge.
     * @param  string              $key  Key to merge.
     * @return array<string,mixed>
     */
    protected function merge_compat_args( array $data, string $key = 'args' ): array {
        $data['args'][ $key ] ??= $this->get_compat_args();

        return $data;
    }

    /**
     * Get the compatibility arguments.
     *
     * @return array<string,string>
     */
    public function get_compat_args(): array {
        return \array_combine( $this->compat_args, $this->compat_args );
    }

    /**
     * Get the constructor keys.
     *
     * @return array<string>
     */
    protected function get_constructor_args(): array {
        return array(
            'context',
            'modifiers',
            'priority',
            'tag',
            'debug',
            'trace',
        );
    }

    /**
     * Generate the injection token.
     *
     * @return string
     */
    private function generate_token(): string {
        $suffix = \ltrim( $this->get_token_suffix(), '-\\' );
        $base   = \trim( $this->get_token_base(), '-\\' );

        return \trim( \XWP_DI_TOKEN_PREFIX . "{$base}::{$suffix}", '-:/' );
    }
}
