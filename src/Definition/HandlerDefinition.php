<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.SpacingAfterParamType
/**
 * HandlerDefinition value object file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Definition;

use XWP\DI\Decorators\Handler;

/**
 * Describes handler metadata independently from runtime decorators.
 *
 * @internal Definition graph detail.
 */
final class HandlerDefinition {
    /**
     * Constructor.
     *
     * @param class-string                       $handler_class Handler class name.
     * @param string                             $tag       Initialization hook tag.
     * @param int                                $priority  Initialization priority.
     * @param int                                $context   Context bitmask.
     * @param string                             $strategy  Initialization strategy.
     * @param bool|null                          $hookable  Whether callbacks should be registered.
     * @param array<string,array<int,string>>    $params    Infuse metadata by method name.
     * @param array<int,string>|null             $callbacks Callback definition IDs.
     */
    public function __construct(
        private string $handler_class,
        private string $tag = '',
        private int $priority = 10,
        private int $context = Handler::CTX_GLOBAL,
        private string $strategy = Handler::INIT_AUTO,
        private ?bool $hookable = null,
        private array $params = array(),
        private ?array $callbacks = null,
    ) {
    }

    /**
     * Get the handler class name.
     *
     * @return class-string
     */
    public function get_class(): string {
        return $this->handler_class;
    }

    public function get_tag(): string {
        return $this->tag;
    }

    public function get_priority(): int {
        return $this->priority;
    }

    public function get_context(): int {
        return $this->context;
    }

    public function get_strategy(): string {
        return $this->strategy;
    }

    public function is_hookable(): ?bool {
        return $this->hookable;
    }

    /**
     * Get Infuse metadata by method name.
     *
     * @return array<string,array<int,string>>
     */
    public function get_params(): array {
        return $this->params;
    }

    /**
     * Get callback definition IDs.
     *
     * @return array<int,string>|null
     */
    public function get_callbacks(): ?array {
        return $this->callbacks;
    }

    public function equals( self $definition ): bool {
        return $this->to_array() === $definition->to_array();
    }

    /**
     * Get comparable definition data.
     *
     * @return array<string,mixed>
     */
    private function to_array(): array {
        return array(
            'callbacks' => $this->callbacks,
            'class'     => $this->handler_class,
            'context'   => $this->context,
            'hookable'  => $this->hookable,
            'params'    => $this->params,
            'priority'  => $this->priority,
            'strategy'  => $this->strategy,
            'tag'       => $this->tag,
        );
    }
}
