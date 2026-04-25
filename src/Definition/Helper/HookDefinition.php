<?php
/**
 * HookDefinition interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Definition\Helper;

/**
 * Common contract for hook definition helpers.
 */
interface HookDefinition {
    /**
     * Set the runtime metatype this definition describes.
     *
     * @param  class-string $metatype Runtime metatype class name.
     * @return self
     */
    public function metatype( string $metatype ): self;
}
