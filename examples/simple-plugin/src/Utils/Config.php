<?php
/**
 * Config class file.
 *
 * @package Example
 */

namespace Example\Utils;

use Example\Interfaces\Config_Interface;

/**
 * Configuration service.
 */
class Config implements Config_Interface {
    /**
     * Configuration values.
     *
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->config = \wp_parse_args(
            \get_option( 'example_plugin_opts', array() ),
            $this->get_defaults(),
        );
    }

    /**
     * Get the default configuration values.
     *
     * @return array<string,mixed>
     */
    protected function get_defaults(): array {
        return array(
            'opt'  => 'value',
            'opt2' => 'value2',
            'opt3' => 'value3',
        );
    }

    /**
     * Get a configuration value.
     *
     * @param  string $key Configuration key.
     * @param  mixed  $def Default value to return if the key is not found.
     * @return mixed
     */
    public function get( string $key, mixed $def = null ): mixed {
        return $this->config[ $key ] ?? $def;
    }
}
