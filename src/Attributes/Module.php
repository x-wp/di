<?php //phpcs:disable Squiz.Commenting.FunctionComment

namespace XWP\DI\Attributes;

use Attribute;
use Stringable;
use XWP\DI\Interfaces\Has_Context;

#[Attribute( Attribute::TARGET_CLASS )]
class Module implements Has_Context {
    /**
     * Classname of the module.
     *
     * @var string
     */
    protected string $classname;

    /**
     * Constructor.
     *
     * @param ?string                 $hook       Hook name.
     * @param ?int                    $priority   Hook priority.
     * @param int                     $context    Module context.
     * @param ?string                 $id         Module ID.
     * @param array<int,class-string> $imports    Array of submodules to import.
     * @param array<int,class-string> $handlers   Array of handlers to register.
     * @param array<
     *  int,
     *  array{provide:Stringable|int,useClass:class-string} |
     *  array{provide:Stringable|int,useValue:mixed} |
     *  array{provide:Stringable|int,useFactory:(callable(...):mixed)|callable-string,inject?:array<int,Stringable|int>} |
     *  array{provide:Stringable|int,useExisting:Stringable|int}
     * >                              $providers Array of providers to register.
     * @param array<int,class-string> $services   Array of autowired services.
     * @param mixed                   ...$args    Deprecated arguments.
     */
    public function __construct(
        protected ?string $hook = null,
        protected ?int $priority = null,
        protected int $context = self::CTX_GLOBAL,
        protected ?string $id = null,
        protected array $imports = array(),
        protected array $handlers = array(),
        protected array $providers = array(),
        protected array $services = array(),
        mixed ...$args,
    ) {
    }

    public function get_hook(): ?string {
        return $this->hook;
    }

    public function get_priority(): ?int {
        return $this->priority;
    }

    public function get_imports(): array {
        return $this->imports;
    }

    public function get_handlers(): array {
        return $this->handlers;
    }

    public function get_services(): array {
        return $this->services;
    }

    public function get_context(): int {
        return $this->context;
    }

    public function get_id(): string {
        return 'xwp.m:' . ( $this->id ?? $this->classname );
    }

    public function get_classname(): string {
        return $this->classname;
    }

    /**
     * Get the module definition.
     *
     * @return array<string,mixed>
     */
    public function get_definition(): array {
        return \method_exists( $this->classname, 'get_definition' )
            ? $this->classname::get_definition()
            : array();
    }

    /**
     * Check if the module has a specific import.
     *
     * @param  class-string $import Import class name.
     * @return bool
     */
    public function has_import( string $import ): bool {
        return \in_array( $import, $this->get_imports(), true );
    }

    public function add_import( string $import, string $position = 'start' ): static {
        if ( \in_array( $import, $this->imports, true ) ) {
            return $this; // Import already exists.
        }

        $cb = 'end' === $position ? 'array_push' : 'array_unshift';
        $cb( $this->imports, $import );

        return $this;
    }

    public function with_classname( string $classname ): static {
        $this->classname = $classname;

        return $this;
    }
}
