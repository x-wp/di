<?php //phpcs:disable SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
/**
 * Config_Interface class file.
 *
 * @package Example
 */

namespace Example\Interfaces;

/**
 * Describes a configuration object.
 */
interface Config_Interface {
    /**
     * Get a configuration value.
     *
     * @param  string $key Configuration key.
     * @param  mixed  $def Default value to return if the key is not found.
     * @return mixed
     */
    public function get( string $key, mixed $def = null ): mixed;
}
