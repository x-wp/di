<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.SpacingAfterParamType
/**
 * CallbackDefinition value object file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Definition;

use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Filter;

/**
 * Describes callback metadata independently from runtime decorators.
 *
 * @internal Definition graph detail.
 */
final class CallbackDefinition {
    /**
     * Convert plain callback metadata without resolving runtime values.
     *
     * @param array<string,mixed> $data Metadata emitted by Filter::get_data() or Action::get_data().
     * @phpstan-param array{
     *   type: class-string,
     *   args: array{
     *     tag: string,
     *     priority: null|\Closure|string|int|array{0:class-string,1:string},
     *     args: int|null,
     *     context: int,
     *     invoke: int,
     *     params: array<int,string>,
     *     modifiers: array<int,string>|string|false,
     *     conditional: null|string|\Closure|array{0:class-string,1:string}
     *   },
     *   params: array{classname: class-string, method: string}
     * } $data
     * @return self
     *
     * @throws \InvalidArgumentException If the callback is not a plain filter or action.
     */
    public static function from_data( array $data ): self {
        $type = match ( $data['type'] ) {
            Filter::class => 'filter',
            Action::class => 'action',
            default       => throw new \InvalidArgumentException(
                'Only plain Filter and Action metadata is supported.',
            ),
        };

        $args   = $data['args'];
        $params = $data['params'];
        $base   = \trim( $params['classname'], '-' );
        $suffix = \ltrim( "{$params['method']}[{$args['tag']}]", '-' );

        return new self(
            id: \trim( "Hook-{$base}::{$suffix}", '-:/' ),
            handler: $params['classname'],
            method: $params['method'],
            type: $type,
            tag: $args['tag'],
            priority: $args['priority'],
            accepted_args: $args['args'],
            context: $args['context'],
            invoke: $args['invoke'],
            params: $args['params'],
            modifiers: $args['modifiers'],
            conditional: $args['conditional'],
        );
    }

    /**
     * Constructor.
     *
     * @param string                                                     $id            Stable callback ID.
     * @param class-string                                               $handler       Handler class name.
     * @param string                                                     $method        Handler method name.
     * @param string                                                     $type          Callback type.
     * @param string                                                     $tag           Hook tag.
     * @param null|\Closure|string|int|array{0:class-string,1:string}     $priority      Raw hook priority.
     * @param int|null                                                   $accepted_args Accepted argument count.
     * @param int                                                        $context       Context bitmask.
     * @param int                                                        $invoke        Invocation bitmask.
     * @param array<int,string>                                          $params        Extra callback params.
     * @param array<int,string>|string|false                             $modifiers     Dynamic tag modifiers.
     * @param null|string|\Closure|array{0:class-string,1:string}        $conditional   Conditional callback metadata.
     */
    public function __construct(
        private string $id,
        private string $handler,
        private string $method,
        private string $type,
        private string $tag,
        private null|\Closure|string|int|array $priority = 10,
        private ?int $accepted_args = null,
        private int $context = Filter::CTX_GLOBAL,
        private int $invoke = Filter::INV_STANDARD,
        private array $params = array(),
        private array|string|bool $modifiers = false,
        private null|string|\Closure|array $conditional = null,
    ) {
    }

    public function get_id(): string {
        return $this->id;
    }

    /**
     * Get the handler class name.
     *
     * @return class-string
     */
    public function get_handler(): string {
        return $this->handler;
    }

    public function get_method(): string {
        return $this->method;
    }

    public function get_type(): string {
        return $this->type;
    }

    public function get_tag(): string {
        return $this->tag;
    }

    /**
     * Get the unresolved hook priority.
     *
     * @return null|\Closure|string|int|array{0:class-string,1:string}
     */
    public function get_priority(): null|\Closure|string|int|array {
        return $this->priority;
    }

    public function get_accepted_args(): ?int {
        return $this->accepted_args;
    }

    public function get_context(): int {
        return $this->context;
    }

    public function get_invoke(): int {
        return $this->invoke;
    }

    /**
     * Get extra callback params.
     *
     * @return array<int,string>
     */
    public function get_params(): array {
        return $this->params;
    }

    /**
     * Get dynamic tag modifiers.
     *
     * @return array<int,string>|string|false
     */
    public function get_modifiers(): array|string|bool {
        return $this->modifiers;
    }

    /**
     * Get conditional callback metadata.
     *
     * @return null|string|\Closure|array{0:class-string,1:string}
     */
    public function get_conditional(): null|string|\Closure|array {
        return $this->conditional;
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
            'accepted_args' => $this->accepted_args,
            'conditional'   => $this->conditional,
            'context'       => $this->context,
            'handler'       => $this->handler,
            'id'            => $this->id,
            'invoke'        => $this->invoke,
            'method'        => $this->method,
            'modifiers'     => $this->modifiers,
            'params'        => $this->params,
            'priority'      => $this->priority,
            'tag'           => $this->tag,
            'type'          => $this->type,
        );
    }
}
