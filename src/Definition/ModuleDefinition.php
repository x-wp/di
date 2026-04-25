<?php
/**
 * ModuleDefinition value object file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Definition;

/**
 * Describes a module and its DI composition metadata.
 *
 * @internal Definition graph detail.
 */
final class ModuleDefinition {
    /**
     * Constructor.
     *
     * @param class-string            $metatype Module class name.
     * @param array<int,class-string> $imports  Imported module class names.
     * @param array<int,class-string> $provides Provided service class names.
     * @param array<int,class-string> $exports  Exported service class names.
     */
    public function __construct(
        private string $metatype,
        private array $imports = array(),
        private array $provides = array(),
        private array $exports = array(),
    ) {
    }

    /**
     * Get the module class name.
     *
     * @return class-string
     */
    public function get_metatype(): string {
        return $this->metatype;
    }

    /**
     * Get imported module class names.
     *
     * @return array<int,class-string>
     */
    public function get_imports(): array {
        return $this->imports;
    }

    /**
     * Get provided service class names.
     *
     * @return array<int,class-string>
     */
    public function get_provides(): array {
        return $this->provides;
    }

    /**
     * Get exported service class names.
     *
     * @return array<int,class-string>
     */
    public function get_exports(): array {
        return $this->exports;
    }
}
