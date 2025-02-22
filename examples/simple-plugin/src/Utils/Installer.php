<?php
/**
 * Installer class file.
 *
 * @package Example
 */

namespace Example\Utils;

/**
 * Dummy installer class.
 *
 * This class uses the singleton pattern to ensure only one instance is created.
 * It needs to be explicitly defined in the DI configuration so the container knows how to create it.
 */
class Installer {
    /**
     * The single instance of the class.
     *
     * @var self
     */
    private static Installer $instance;

    /**
     * Get the single instance of the class.
     *
     * @return self
     */
    public static function instance(): self {
        return self::$instance ??= new self();
    }

    /**
     * Protected constructor to prevent creating a new instance.
     */
    protected function __construct() {
        // Do something.
    }

    /**
     * Dummy installation method.
     *
     * @param  mixed ...$args Installation arguments.
     */
    public function install( mixed ...$args ): void {
        \set_transient( 'app_init', $args, \MONTH_IN_SECONDS );
    }
}
