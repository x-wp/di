<?php //phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext

/**
 * Strategy enum file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Enums;

/**
 * Defines the initialization strategies for handlers.
 */
enum Strategy: string {
    case Early    = 'early';
    case Now      = 'immediately';
    case Lazy     = 'on-demand';
    case JIT      = 'just-in-time';
    case Deferred = 'deferred';
    case Auto     = 'auto';
    case User     = 'dynamically';

    /**
     * Check if the strategy is lazy or just-in-time.
     *
     * @return bool True if the strategy is lazy or just-in-time, false otherwise.
     */
    public function isLazy(): bool {
        return self::Lazy === $this || self::JIT === $this;
    }

    /**
     * Check if the strategy is deferred or auto.
     *
     * @return bool True if the strategy is deferred or auto, false otherwise.
     */
    public function isDeferred(): bool {
        return self::Deferred === $this || self::Auto === $this;
    }
}
