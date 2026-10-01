<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.SpacingAfterParamType
/**
 * CallbackDefinition value object file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Definition;

use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Ajax_Action;
use XWP\DI\Decorators\CLI_Command;
use XWP\DI\Decorators\Dynamic_Action;
use XWP\DI\Decorators\Dynamic_Filter;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\REST_Route;

/**
 * Describes callback metadata independently from runtime decorators.
 *
 * @internal Definition graph detail.
 */
final class CallbackDefinition {
    /**
     * Original wire metadata for discovery/cache round trips.
     *
     * @var array{type:class-string,args:array<string,mixed>,params:array<string,mixed>}|null
     */
    private ?array $data = null;

    /**
     * Convert built-in callback metadata without resolving runtime values.
     *
     * @param array{type:class-string,args:array<string,mixed>,params:array<string,mixed>} $data Decorator metadata.
     * @return self
     *
     * @throws \InvalidArgumentException If the callback declaration type is unsupported.
     */
    public static function from_data( array $data ): self {
        $source = $data;
        $type   = match ( true ) {
            \is_a( $data['type'], Action::class, true ),
            \is_a( $data['type'], Dynamic_Action::class, true ) => 'action',
            \is_a( $data['type'], Filter::class, true ) => 'filter',
            default => throw new \InvalidArgumentException( 'Unsupported callback declaration type.' ),
        };

        $options = $data['args'];
        if ( \is_a( $data['type'], Ajax_Action::class, true ) ) {
            $data['args'] += array(
                'args'    => 0,
                'context' => Filter::CTX_AJAX,
                'tag'     => '%s_%s_%s',
            );
        }
        if ( \is_a( $data['type'], REST_Route::class, true ) ) {
            $data['args'] += array(
                'context'  => Filter::CTX_REST,
                'priority' => $data['params']['priority'] ?? 10,
                'tag'      => $data['params']['tag'] ?? 'rest_api_init',
            );
        }
        if ( \is_a( $data['type'], CLI_Command::class, true ) ) {
            $data['args']['args'] = null;
            $data['args']        += array(
                'context' => Filter::CTX_CLI,
                'tag'     => 'cli_init',
            );
        }
        $args   = $data['args'] + array(
            'args'        => null,
            'conditional' => null,
            'context'     => Filter::CTX_GLOBAL,
            'invoke'      => Filter::INV_PROXIED,
            'modifiers'   => false,
            'params'      => array(),
            'priority'    => 10,
        );
        $params = $data['params'];
        $base   = \trim( $params['classname'], '-' );
        $suffix = \ltrim( "{$params['method']}[{$args['tag']}]", '-' );

        $definition       = new self(
            id: $params['token'] ?? \trim( "Hook-{$base}::{$suffix}", '-:/' ),
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
            decorator: $data['type'],
            options: $options,
        );
        $definition->data = $source;
        return $definition;
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
     * @param class-string|null $decorator Original decorator type.
     * @param array<string,mixed> $options Original specialized constructor arguments.
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
        private ?string $decorator = null,
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
                'args'        => $this->accepted_args,
                'conditional' => $this->conditional,
                'context'     => $this->context,
                'invoke'      => $this->invoke,
                'modifiers'   => $this->modifiers,
                'params'      => $this->params,
                'priority'    => $this->priority,
                'tag'         => $this->tag,
            ),
            'params' => array(
                'classname' => $this->handler,
                'method'    => $this->method,
            ),
            'type'   => $this->get_decorator(),
        );
    }

    public function get_token(): string {
        return $this->get_id();
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

    /**
     * Get the exact built-in decorator type for a compatibility view.
     *
     * @return class-string<Filter<object,\XWP\DI\Interfaces\Can_Handle<object>>>
     */
    public function get_decorator(): string {
        return $this->decorator ?? ( 'action' === $this->type ? Action::class : Filter::class );
    }

    /**
     * Get unresolved specialized constructor arguments.
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
            'accepted_args' => $this->accepted_args,
            'conditional'   => $this->conditional,
            'context'       => $this->context,
            'decorator'     => $this->get_decorator(),
            'handler'       => $this->handler,
            'id'            => $this->id,
            'invoke'        => $this->invoke,
            'method'        => $this->method,
            'modifiers'     => $this->modifiers,
            'options'       => $this->options,
            'params'        => $this->params,
            'priority'      => $this->priority,
            'tag'           => $this->tag,
            'type'          => $this->type,
        );
    }
}
