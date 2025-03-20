<?php
/**
 * Invoker class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Import;
use XWP\DI\Traits\Hook_Factory_Methods;

/**
 * Handles hook registration and invocation.
 */
class Invoker {
    use Hook_Factory_Methods;

    /**
     * Handlers.
     *
     * @var array<class-string,false|string>
     */
    private array $handlers = array();

    /**
     * Hooks.
     *
     * @var array<class-string,array<string,string>>>
     */
    private array $hooks = array();

    /**
     * Uncached handlers.
     *
     * @var array<string,string> //array<Can_Handle<object>>
     */
    private array $uncached = array();

    /**
     * Cache configuration.
     *
     * @var array{
     *   app: bool,
     *   defs: bool,
     *   hooks: bool,
     *   dir: bool,
     * }
     */
    private array $cache;

    /**
     * WP Environment.
     *
     * @var string
     */
    private string $env;

    /**
     * Is debug mode enabled.
     *
     * @var bool
     */
    private bool $debug;

    /**
     * Constructor.
     *
     * @param  Container $container Container instance.
     */
    public function __construct( private Container $container ) {
        $this->debug = $container->get( 'app.debug' );
        $this->cache = $container->get( 'app.cache' );
        $this->env   = $container->get( 'app.env' );

        \add_action( "xwp_{$this->app_uuid()}_module_init", array( $this, 'init_module' ), 0, 1 );

        if ( ! $this->debug ) {
            return;
        }

        \add_action( 'shutdown', array( $this, 'debug' ) );
    }

    /**
     * Debug output.
     */
    public function debug(): void {
        // if ( 'woosync' === $this->app_id() ) {
        // \dump( $this->hooks, );
        // die;
        // }

        if ( ! $this->uncached ) {
            return;
        }

        // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
        \error_log( \str_pad( " XWP-DI: {$this->app_id()} ", 62, '*', STR_PAD_BOTH ) );
        \error_log( 'Hook definition cache is active. The following handlers were loaded manually' );
        \error_log( \esc_html( \implode( ', ', $this->uncached ) ) );
        // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }

    /**
     * Get registered handlers, and their initialization hook.
     *
     * @return array<class-string,false|string>
     */
    public function get_handlers(): array {
        return $this->handlers;
    }

    /**
     * Get registered hooks.
     *
     * @param  string|null $handler Handler classname.
     * @return ($handler is null ? array<class-string,array<string,string>> : array<string,string>)
     */
    public function get_actions( ?string $handler = null ): array {
        return $handler ? $this->hooks[ $handler ] ?? array() : $this->hooks;
    }

    /**
     * Run the module.
     *
     * @template T of object
     *
     * @param  Can_Import<T> $module Module instance.
     * @return static
     */
    public function load_module( Can_Import $module ): static {
        return $this->register_handler( $module )->load_imports( $module );
    }

    /**
     * Initialize the module.
     *
     * @hooked xwp_{app_uuid}_module_init Registers handlers for the module.
     *
     * @template T of object
     * @param  Can_Import<T> $m Module to initialize.
     */
    public function init_module( Can_Import $m ): void {
        foreach ( $m->get_handlers() as $handler ) {
            $handler =

            $this->register_handler( $handler );
        }
    }

    /**
     * Register a handler.
     *
     * @template T of object
     * @param  class-string<T>|T|Can_Handle<T>|array<string,mixed> ...$handlers Handlers to register.
     * @return static
     */
    public function register_handlers( string|object|array ...$handlers ): static {
        foreach ( $handlers as $handler ) {
            $this->register_handler( $handler );
        }

        return $this;
    }

    /**
     * Register a handler.
     *
     * @template T of object
     * @param  class-string<T>|T|Can_Handle<T>|array<string,mixed> $h Handler to register.
     * @return static
     */
    public function register_handler( string|object|array $h ): static {
        $h = \is_string( $h ) && \class_exists( $h ) && $this->container->has( 'Handler-' . $h )
            ? $this->container->get( 'Handler-' . $h )
            : $this->make_handler( $h );

        //phpcs:disable SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
        return match ( $h->get_strategy() ) {
            $h::INIT_LAZY,
            $h::INIT_JIT   => $this->add_handler( $h )->queue_lazy_handler( $h )->queue_methods( $h ),
            $h::INIT_EARLY => $this->add_handler( $h )->init_handler( $h )->queue_methods( $h ),
            $h::INIT_NOW   => $this->add_handler( $h )->init_handler( $h )->register_methods( $h )->invoke_methods( $h ),
            $h::INIT_USER  => $this->add_handler( $h )->register_methods( $h )->invoke_methods( $h ),
            default        => $this->add_handler( $h )->queue_handler( $h ),
        };
        //phpcs:enable SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
    }

    /**
     * Add a handler.
     *
     * @template T of object
     * @param  Can_Handle<T> $handler The handler to add.
     * @param  bool          $clear   Whether to clear existing hooks.
     * @return static
     */
    public function add_handler( Can_Handle $handler, bool $clear = true ): static {
        $cname = $handler->get_classname();

        if ( isset( $this->handlers[ $cname ] ) ) {
            return $this;
        }

        if ( $clear ) {
            $this->hooks[ $cname ] = array();
        }

        $this->handlers[ $cname ] = $handler->is_loaded() ? $handler->get_init_hook() : false;

        return $this;
    }

    /**
     * Load module imports.
     *
     * @template T of object
     * @param  Can_Import<T> $module Module instance.
     * @return static
     */
    private function load_imports( Can_Import $module ): static {
        foreach ( $module->get_imports() as $import ) {
            $this->load_module( $this->fetch_module( $import ) );
        }

        return $this;
    }

    /**
     * Queue a handler.
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler to queue.
     * @return static
     */
    private function queue_handler( Can_Handle $h ): static {
        \add_action(
            $h->get_tag(),
            function () use ( $h ) {
                $this
                    ->init_handler( $h )
                    ->register_methods( $h )
                    ->invoke_methods( $h );
            },
            $h->get_priority(),
            0,
        );

        return $this;
    }

    /**
     * Queue a handler.
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler to queue.
     * @return static
     */
    private function queue_lazy_handler( Can_Handle $h ): static {
        if ( $h->is_lazy() ) {
            \add_action(
                $h->get_lazy_tag(),
                function () use ( $h ) {
                    $this->init_handler( $h );
                },
                $h->get_priority(),
                0,
            );
        }

        return $this;
    }

    /**
     * Initialize a handler.
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler to initialize.
     * @return static
     */
    private function init_handler( Can_Handle $h ): static {
        if ( $h->load() ) {
            $this->handlers[ $h->get_classname() ] = $h->get_init_hook();
        }

        return $this;
    }

    /**
     * Register handler methods
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler instance.
     * @return static
     */
    private function register_methods( Can_Handle $h ): static {
        if ( null !== $h->get_hooks() ) {
            return $this;
        }

        $h->with_hooks( $this->get_hooks( $h ) );

        if ( $this->is_cached( 'hooks' ) && ( $this->debug || $this->is_prod() ) ) {
            $this->uncached[ $h->get_token() ] = $h->get_classname();
        }

        return $this;
    }

    /**
     * Queue handler methods
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler instance.
     * @return static
     */
    private function queue_methods( Can_Handle $h ): static {
        if ( $h->is_hookable() ) {
            \add_action(
                $h->get_tag(),
                function () use ( $h ) {
                    $this
                    ->register_methods( $h )
                    ->invoke_methods( $h );
                },
                $h->get_priority(),
                0,
            );
        }

        return $this;
    }

    /**
     * Invoke handler methods
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler instance.
     * @return static
     */
    private function invoke_methods( Can_Handle $h ): static {
        if ( \is_null( $h->get_hooks() ) ) {
            \dump( $h );
            die;
        }
        foreach ( $h->get_hooks() as $hook_id ) {
            $m = $this->fetch_hook( $hook_id );

            if ( ! $m->load() ) {
                continue;
            }

            $this->hooks[ $m->get_classname() ][ $m->get_token() ] = $m->get_init_hook();
        }

        return $this;
    }

    /**
     * Get the application ID.
     *
     * @return string
     */
    private function app_id(): string {
        return $this->container->get( 'app.id' );
    }

    /**
     * Get the application UUID.
     *
     * @return string
     */
    private function app_uuid(): string {
        return $this->container->get( 'app.uuid' );
    }

    /**
     * Check if cache is enabled for a feature.
     *
     * @param  'app'|'defs'|'hooks' $feature Feature to check.
     * @return bool
     */
    private function is_cached( string $feature ): bool {
        return $this->cache[ $feature ] ?? false;
    }

    /**
     * Are we in production?
     *
     * @return bool
     */
    private function is_prod(): bool {
        return 'production' === $this->env;
    }
}
