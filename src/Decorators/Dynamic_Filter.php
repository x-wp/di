<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Dynamic_Filter class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use ReflectionMethod;
use Reflector;

/**
 * Dynamic filter decorator
 *
 * @template T of object
 * @template H of Ajax_Handler<T>
 * @extends Filter<T,H>
 */
#[\Attribute( \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE )]
class Dynamic_Filter extends Filter {
    /**
     * Legacy custom-subclass and typed-view compatibility.
     *
     * @use \XWP\DI\Compatibility\Dynamic_Filter_Methods<T,H>
     */
    use \XWP\DI\Compatibility\Dynamic_Filter_Methods;

    /**
     * Constructor.
     *
     * @param string                                        $tag      Hook tag.
     * @param string|array<string>|callable():array<string> $vars     Variables to mix into the tag.
     * @param int                                           $context  Hook context.
     * @param Closure|string|int|array{class-string,string} $priority Hook priority.
     * @param int|null                                      $args        The number of arguments to pass to the callback.
     * @param array<int,string>                             $params   The parameters to pass to the callback.
     */
    public function __construct(
        string $tag,
        callable|array|string $vars,
        int $context = self::CTX_GLOBAL,
        Closure|array|int|string $priority = 10,
        ?int $args = null,
        array $params = array(),
    ) {
        $this->raw_vars = $vars;

        parent::__construct(
            tag: $tag,
            priority: $priority,
            context: $context,
            invoke: self::INV_PROXIED,
            args: $args,
            params: $params,
        );
    }

    /**
     * Export unbound attribute metadata.
     *
     * @internal Discovery metadata detail.
     * @return array<string,mixed>
     */
    public function get_declaration(): array {
        return array(
            'args'     => $this->args,
            'context'  => $this->context,
            'params'   => $this->params,
            'priority' => $this->prio,
            'tag'      => $this->tag,
            'vars'     => $this->raw_vars,
        );
    }
}
