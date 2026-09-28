<?php
/**
 * Shared CLI namespace registration.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

/**
 * Shares namespace identity between runtime and custom decorator handlers.
 *
 * @internal
 */
final class CLI_Namespaces {
    /**
     * Registered command roots.
     *
     * @var array<string,bool>
     */
    private static array $roots = array();

    /**
     * Register a namespace once across all handler implementations.
     *
     * @param string               $command_namespace Command namespace.
     * @param string               $description Short description.
     * @param callable():bool|null $register Custom namespace registration callback.
     * @return bool
     */
    public static function register( string $command_namespace, string $description, ?callable $register = null ): bool {
        return self::$roots[ $command_namespace ] ??= $register ? $register() : \WP_CLI::add_command(
            $command_namespace,
            \XWP_CLI_Namespace::class,
            array( 'shortdesc' => $description ),
        );
    }
}
