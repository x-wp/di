<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.SpacingAfterParamType
/**
 * HandlerDefinition value object file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Definition;

use XWP\DI\Decorators\Ajax_Handler;
use XWP\DI\Decorators\CLI_Handler;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;
use XWP\DI\Decorators\REST_Handler;

/**
 * Describes handler metadata independently from runtime decorators.
 *
 * @internal Definition graph detail.
 */
final class HandlerDefinition {
    /**
     * Original wire metadata for discovery/cache round trips.
     *
     * @var array{type:class-string,args:array<string,mixed>,params:array<string,mixed>}|null
     */
    private ?array $data = null;

    /**
     * Convert built-in handler metadata without evaluating runtime values.
     *
     * @param array{type:class-string,args:array<string,mixed>,params:array<string,mixed>} $data Handler metadata.
     * @return self
     * @throws \InvalidArgumentException For unsupported custom decorators.
     */
    public static function from_data( array $data ): self {
        $defaults = match ( $data['type'] ) {
            Handler::class => array(),
            Module::class => array( 'tag' => $data['args']['hook'] ),
            Ajax_Handler::class => array(
                'context'  => Handler::CTX_AJAX,
                'strategy' => Handler::INIT_LAZY,
                'tag'      => 'admin_init',
            ),
            REST_Handler::class => array(
                'context' => Handler::CTX_REST,
                'tag'     => 'rest_api_init',
            ),
            CLI_Handler::class => array(
                'context' => Handler::CTX_CLI,
                'tag'     => 'cli_init',
            ),
            default => throw new \InvalidArgumentException( 'Only built-in handler metadata is supported.' ),
        };
        $args             = $data['args'] + $defaults + array(
            'conditional' => null,
            'context'     => Handler::CTX_GLOBAL,
            'hookable'    => null,
            'modifiers'   => false,
            'priority'    => 10,
            'strategy'    => Handler::INIT_AUTO,
            'tag'         => '',
        );
        $definition       = new self(
            handler_class: $data['params']['classname'],
            tag: $args['tag'] ?? '',
            priority: $args['tag'] ? $args['priority'] : null,
            context: $args['context'],
            strategy: $args['strategy'],
            hookable: $args['hookable'],
            params: $data['params']['params'] ?? array(),
            callbacks: $data['params']['callbacks'] ?? null,
            conditional: $args['conditional'],
            modifiers: $args['modifiers'],
            decorator: $data['type'],
            options: $data['args'],
        );
        $definition->data = $data;
        return $definition;
    }

    /**
     * Constructor.
     *
     * @param class-string                       $handler_class Handler class name.
     * @param string                             $tag       Initialization hook tag.
     * @param null|\Closure|string|int|array{0:class-string,1:string} $priority  Initialization priority.
     * @param int                                $context   Context bitmask.
     * @param string                             $strategy  Initialization strategy.
     * @param bool|null                          $hookable  Whether callbacks should be registered.
     * @param array<string,array<int,string>>    $params    Infuse metadata by method name.
     * @param array<int,string>|null             $callbacks Callback definition IDs.
     * @param null|\Closure|string|array{0:class-string,1:string} $conditional Initialization condition.
     * @param array<int,string>|string|false $modifiers Tag modifiers.
     * @param class-string $decorator Original decorator class.
     * @param array<string,mixed> $options Original constructor arguments.
     */
    public function __construct(
        private string $handler_class,
        private string $tag = '',
        private null|\Closure|string|int|array $priority = 10,
        private int $context = Handler::CTX_GLOBAL,
        private string $strategy = Handler::INIT_AUTO,
        private ?bool $hookable = null,
        private array $params = array(),
        private ?array $callbacks = null,
        private null|\Closure|string|array $conditional = null,
        private array|string|bool $modifiers = false,
        private string $decorator = Handler::class,
        private array $options = array(),
    ) {
    }

    /**
     * Serialize discovered metadata using the existing hook-cache layout.
     *
     * @return array{type:class-string,args:array<string,mixed>,params:array<string,mixed>}
     */
    public function get_data(): array {
        return $this->data ?? array(
            'args'   => $this->options + array(
                'conditional' => $this->conditional,
                'context'     => $this->context,
                'hookable'    => $this->hookable,
                'modifiers'   => $this->modifiers,
                'priority'    => $this->priority ?? 10,
                'strategy'    => $this->strategy,
                'tag'         => $this->tag,
            ),
            'params' => array(
                'callbacks' => $this->callbacks,
                'classname' => $this->handler_class,
                'params'    => $this->params,
            ),
            'type'   => $this->decorator,
        );
    }

    public function get_token(): string {
        return $this->get_id();
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

    /**
     * Get the unresolved initialization priority.
     *
     * @return null|\Closure|string|int|array{0:class-string,1:string}
     */
    public function get_priority(): null|\Closure|string|int|array {
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

    public function get_id(): string {
        return \trim( 'Hook-' . \trim( $this->handler_class, '-' ) . '::', '-:/' );
    }

    /**
     * Get the unresolved initialization condition.
     *
     * @return null|\Closure|string|array{0:class-string,1:string}
     */
    public function get_conditional(): null|\Closure|string|array {
        return $this->conditional;
    }

    /**
     * Get unresolved tag modifiers.
     *
     * @return array<int,string>|string|false
     */
    public function get_modifiers(): array|string|bool {
        return $this->modifiers;
    }

    /**
     * Get the original decorator class.
     *
     * @return class-string
     */
    public function get_decorator(): string {
        return $this->decorator;
    }

    /**
     * Get specialized constructor arguments.
     *
     * @return array<string,mixed>
     */
    public function get_options(): array {
        return $this->options;
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
            'callbacks'   => $this->callbacks,
            'class'       => $this->handler_class,
            'conditional' => $this->conditional,
            'context'     => $this->context,
            'decorator'   => $this->decorator,
            'hookable'    => $this->hookable,
            'modifiers'   => $this->modifiers,
            'options'     => $this->options,
            'params'      => $this->params,
            'priority'    => $this->priority,
            'strategy'    => $this->strategy,
            'tag'         => $this->tag,
        );
    }
}
