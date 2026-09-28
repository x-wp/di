<?php
/**
 * Application class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

/**
 * Main application class.
 *
 * @mixin Container
 */
class App {
    /**
     * Whether the application lifecycle has started.
     *
     * @var bool
     */
    private bool $started = false;

    /**
     * Constructor.
     *
     * @param Container $ctr The container instance.
     */
    public function __construct( private readonly Container $ctr ) {
    }

    /**
     * Magic method to call methods on the container.
     *
     * @param  string       $name The method name.
     * @param  array<mixed> $args The method arguments.
     * @return mixed              The result of the method call.
     */
    public function __call( string $name, array $args ): mixed {
        return $this->ctr->$name( ...$args );
    }

    /**
     * Get the application's dependency container.
     *
     * @return Container
     */
    public function container(): Container {
        return $this->ctr;
    }

    /**
     * Start the application's module lifecycle.
     *
     * @return static
     *
     * @throws \RuntimeException If the application is already started.
     */
    public function run(): static {
        if ( $this->started() ) {
            throw new \RuntimeException( 'Application already started.' );
        }

        $this->started = true;

        /**
         * Module class name.
         *
         * @var class-string<object> $root_module
         */
        $root_module = $this->ctr->get( $this->ctr->has( 'app.root' ) ? 'app.root' : 'app.module' );

        $this->ctr->register_handler( $root_module );

        \do_action( "xwp_{$this->ctr->get('app.uuid')}_app_start" );

        return $this;
    }

    /**
     * Check whether the application has started.
     *
     * @return bool
     */
    public function started(): bool {
        return $this->started;
    }
}
