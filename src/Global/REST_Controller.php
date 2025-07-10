<?php //phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound, WordPress.NamingConventions.ValidHookName.UseUnderscores
/**
 * REST Controller class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

use XWP\DI\Interfaces\On_Initialize;

/**
 * Base class for REST controllers.
 */
#[AllowDynamicProperties]
abstract class XWP_REST_Controller extends WP_REST_Controller implements On_Initialize {
    /**
     * Fires when the object is initialized.
     *
     * @return void
     */
    public function on_initialize(): void {
        add_action( 'xwp_di_hooks_loaded_' . $this::class, array( $this, 'register_routes' ) );
    }

    /**
     * Set the namespace.
     *
     * @param  string $namespace Namespace.
     * @return static
     */
    public function with_namespace( string $namespace ): static {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Set the basename.
     *
     * @param  string $base Basename.
     * @return static
     */
    public function with_basename( string $base ): static {
        $this->rest_base = $base;

        return $this;
    }

    /**
     * Register routes for this controller.
     *
     * @return void
     */
    public function register_routes() {
        do_action( $this->namespace . '/' . $this->rest_base );
    }
}
