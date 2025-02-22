<?php
/**
 * Config_Pro class file.
 *
 * @package ExamplePro
 */

namespace ExamplePro\Utils;

use Example\Utils\Config;

/**
 * Demonstrates extending the Config class from the simple plugin.
 */
class Config_Pro extends Config {
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
            'opt4' => 'value4',
            'opt5' => 'value5',
        );
    }
}
