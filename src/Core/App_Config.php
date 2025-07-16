<?php
/**
 * App_Config class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Core;

use XWP\DI\Container;

/**
 * Application config
 *
 * @template TCtr of Container
 *
 * @property-read false|string $root_dir  Application root directory.
 * @property-read string       $cache_dir Application cache directory.
 * @property-read string       $cmp_ctr   Compiled container class name.
 * @property-read bool         $is_prod   Whether the application is running in production mode.
 */
class App_Config {
    /**
     * Application environment.
     *
     * @var 'local'|'development'|'staging'|'production'
     */
    public readonly string $env;

    /**
     * Application UUID.
     *
     * This is a unique identifier for the application, which can be used to
     * reference the application in various contexts.
     *
     * @var string
     */
    public readonly string $uuid;

    /**
     * Application ID.
     *
     * This is a unique identifier for the application, which can be used to
     * reference the application in various contexts.
     *
     * @var string
     */
    public readonly string $id;

    /**
     * Base file path for the application.
     *
     * @var false|string
     */
    public readonly bool|string $file;

    /**
     * Base path for the application.
     *
     * @var false|string
     */
    public readonly bool|string $base;

    /**
     * Application version.
     *
     * @var false|string
     */
    public readonly bool|string $version;

    /**
     * Whether to compile the application.
     *
     * @var bool
     */
    public readonly bool $compile;

    /**
     * Whether to cache the application.
     *
     * @var bool
     */
    public readonly bool $cache;

    /**
     * Whether to generate a serialized global snapshot of the application.
     *
     * Defaults to false, meaning no snapshot is generated.
     *
     * @var bool
     */
    public readonly bool $snapshot;

    /**
     * Container class name.
     *
     * @var class-string<TCtr>
     */
    public readonly string $container;

    /**
     * Constructor for the App_Config class.
     *
     * @param string             $id        Application ID.
     * @param string|null        $uuid      Application UUID.
     * @param string|null        $file      Base file path for the application.
     * @param string|null        $version   Application version.
     * @param bool|null          $compile   Whether to compile the application.
     * @param bool|null          $cache     Whether to cache the application.
     * @param bool|null          $snapshot  Whether to generate a serialized global snapshot of the application.
     * @param class-string<TCtr> $container Optional. Container class name.
     */
    public function __construct(
        string $id,
        ?string $uuid = null,
        ?string $file = null,
        ?string $version = null,
        ?bool $compile = null,
        ?bool $cache = null,
        ?bool $snapshot = null,
        string $container = Container::class,
    ) {
        $this->id        = \sanitize_title( $id );
        $this->uuid      = $uuid ?? \wp_generate_uuid4();
        $this->env       = \wp_get_environment_type();
        $this->file      = $file ?? false;
        $this->base      = $file ? \plugin_basename( $file ) : false;
        $this->version   = $version ?? false;
        $this->compile   = $compile ?? $this->is_prod;
        $this->cache     = $cache ?? $this->is_prod;
        $this->snapshot  = $snapshot ?? false;
        $this->container = $container;
    }

    /**
     * Magic getter method to access properties dynamically.
     *
     * @param  string $name Name of the property to access.
     * @return mixed
     */
    public function __get( string $name ): mixed {
        // If a getter method exists for the requested property, call it.
        if ( \method_exists( $this, 'get_' . $name ) ) {
            return $this->{"get_{$name}"}();
        }

        // If a protected property is requested, return it.
        if ( \property_exists( $this, $name ) ) {
            return $this->{$name};
        }

        return null;
    }

    /**
     * Get the application cache directory.
     *
     * @return string
     */
    protected function get_cache_dir(): string {
        $root_dir = $this->root_dir;

        return ! $root_dir
            ? \WP_CONTENT_DIR . '/cache/xwp-app-' . $this->id
            : $root_dir . '/cache';
    }

    /**
     * Get the application root directory.
     *
     * @return false|string
     */
    protected function get_root_dir(): bool|string {
        return $this->file
            ? \untrailingslashit( \plugin_dir_path( $this->file ) )
            : false;
    }

    /**
     * Get compiled container class name.
     *
     * @return string
     */
    protected function get_cmp_ctr(): string {
        return 'CompiledContainer' . \ucwords( $this->id, '-._' );
    }

    /**
     * Are we running in a production environment?
     *
     * @return bool
     */
    protected function get_is_prod(): bool {
        return 'production' === $this->env;
    }
}
