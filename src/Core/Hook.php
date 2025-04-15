<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
/**
 * Hook class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Core;

use Automattic\Jetpack\Constants;
use Closure;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use XWP\DI\Container;
use XWP\DI\Interfaces\Can_Hook;
use XWP\DI\Interfaces\Invokes_Hook;
use XWP\DI\Traits\Hook_Invoke_Methods;
use XWP_Context;

/**
 * Base hook from which the action and filter decorators inherit.
 *
 * @template THndlr of object
 * @implements Invokes_Hook<THndlr>
 */
abstract class Hook implements Invokes_Hook {
    /**
     * Define the shared methods needed for hook invocation.
     *
     * @use Hook_Invoke_Methods<THndlr>
     */
    use Hook_Invoke_Methods;

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


    protected int $context = Can_Hook::CTX_GLOBAL;

    /**
     * The modifiers to replace in the tag name.
     *
     * @var array<int,string>|string|false
     */
    protected array|string|bool $modifiers = false;

    /**
     * Is the hook debug enabled?
     *
     * @var bool
     */
    protected bool $debug = false;

    /**
     * Is the hook trace enabled?
     *
     * @var bool
     */
    protected bool $trace = false;


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
     * Is hook hydrated?
     *
     * @var bool
     */
    protected bool $hydrated = false;

    /**
     * Injection token.
     *
     * @var string
     */
    private string $token;

    /**
     * Constructor.
     *
     * @param array{classname: class-string<THndlr>} $args Hook arguments.
     */
    public function __construct( array $args ) {
        try {
            foreach ( $args as $arg => $value ) {
                \method_exists( $this, "set_{$arg}" )
                    ? $this->{"set_{$arg}"}( $value, $args )
                    : $this->$arg = $value;
            }
        } catch ( \Throwable ) {
            \dump( $args );
            die;
        }
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

    public function with_classname( string $classname ): static {
        $this->classname = $classname;

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

    public function get_priority(): ?int {
        return $this->priority ?? null;
    }

    public function get_container(): Container {
        return $this->container;
    }

    public function get_classname(): string {
        return $this->classname;
    }

    public function get_shortname(): string {
        $name = \explode( '\\', $this->get_classname() );

        return \end( $name );
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
     * Set priority.
     *
     * @param ?int                $priority Priority.
     * @param array<string,mixed> $args    Arguments.
     * @return static
     */
    public function set_priority( ?int $priority, array $args = array() ): static {
        $priority ??= '' !== ( $args['tag'] ?? '' ) ? 10 : null;

        $this->priority = $priority;

        return $this;
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
     * Generate the injection token.
     *
     * @return string
     */
    private function generate_token(): string {
        $suffix = \ltrim( $this->get_token_suffix(), '-\\' );
        $base   = \trim( $this->get_token_base(), '-\\' );

        return \trim( \XWP_DI_TOKEN_PREFIX . "{$base}::{$suffix}", '-:/' );
    }

    protected function resolve( string ...$targets ): array {
        $resolved = array();

        foreach ( $targets as $target ) {
            $resolved[ $target ] = $this->get_container()->get( $target );
        }

        return $resolved;
    }
}
