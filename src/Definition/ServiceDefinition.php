<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.SpacingAfterParamType, SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder
/**
 * ServiceDefinition value object file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Definition;

use DI\Definition\Definition;
use DI\Definition\FactoryDefinition;
use DI\Definition\ObjectDefinition;
use DI\Definition\ValueDefinition;

/**
 * Describes a module service provider.
 *
 * @internal Definition graph detail.
 */
final class ServiceDefinition {
    /**
     * Autowired service kind.
     */
    public const KIND_AUTOWIRE = 'autowire';

    /**
     * Factory service kind.
     */
    public const KIND_FACTORY = 'factory';

    /**
     * Value service kind.
     */
    public const KIND_VALUE = 'value';

    /**
     * Constructor.
     *
     * @param string                              $id         Container entry ID.
     * @param self::KIND_*                        $kind       Service definition kind.
     * @param class-string|null                   $class_name Autowired class name.
     * @param mixed                               $value      Factory callable or literal value.
     * @param bool                                $is_public  Whether imports may consume this service.
     */
    private function __construct(
        private string $id,
        private string $kind,
        private ?string $class_name = null,
        private mixed $value = null,
        private bool $is_public = false,
    ) {
    }

    /**
     * Create an autowired service definition.
     *
     * @param  class-string $class_name Service class name.
     * @param  string|null  $id         Optional container entry ID.
     * @param  bool         $is_public  Whether imports may consume this service.
     * @return self
     */
    public static function autowire( string $class_name, ?string $id = null, bool $is_public = false ): self {
        return new self( $id ?? $class_name, self::KIND_AUTOWIRE, $class_name, null, $is_public );
    }

    /**
     * Create a factory service definition.
     *
     * @param  string                       $id        Container entry ID.
     * @param  callable|array{0:mixed,1:string}|string $factory   Factory callable.
     * @param  bool                         $is_public Whether imports may consume this service.
     * @return self
     */
    public static function factory( string $id, callable|array|string $factory, bool $is_public = false ): self {
        return new self( $id, self::KIND_FACTORY, null, $factory, $is_public );
    }

    public static function value( string $id, mixed $value, bool $is_public = false ): self {
        return new self( $id, self::KIND_VALUE, null, $value, $is_public );
    }

    public function get_id(): string {
        return $this->id;
    }

    /**
     * Get the service kind.
     *
     * @return self::KIND_*
     */
    public function get_kind(): string {
        return $this->kind;
    }

    /**
     * Get the autowired class name.
     *
     * @return class-string|null
     */
    public function get_class(): ?string {
        return $this->class_name;
    }

    public function get_value(): mixed {
        return $this->value;
    }

    public function is_public(): bool {
        return $this->is_public;
    }

    public function to_php_di(): Definition {
        return match ( $this->kind ) {
            self::KIND_AUTOWIRE => new ObjectDefinition( $this->id, $this->class_name ),
            self::KIND_FACTORY  => new FactoryDefinition( $this->id, $this->value ),
            self::KIND_VALUE    => $this->value_definition(),
        };
    }

    public function equals( self $definition ): bool {
        return $this->to_array() === $definition->to_array();
    }

    private function value_definition(): ValueDefinition {
        $definition = new ValueDefinition( $this->value );
        $definition->setName( $this->id );

        return $definition;
    }

    /**
     * Get comparable definition data.
     *
     * @return array<string,mixed>
     */
    private function to_array(): array {
        return array(
            'class'  => $this->class_name,
            'id'     => $this->id,
            'kind'   => $this->kind,
            'public' => $this->is_public,
            'value'  => $this->value,
        );
    }
}
