<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh, SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
/**
 * Filter decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use ReflectionMethod;
use Reflector;
use XWP\DI\Container;
use XWP\DI\Hook\Callback;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;

/**
 * Filter hook decorator.
 *
 * @template T of object
 * @template H of Can_Handle<T>
 * @extends Hook<T,ReflectionMethod>
 * @implements Can_Invoke<T,H>
 */
#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Filter extends Hook implements Can_Invoke {
    /**
     * Legacy custom-subclass and typed-view compatibility.
     *
     * @use \XWP\DI\Compatibility\Filter_Methods<T,H>
     */
    use \XWP\DI\Compatibility\Filter_Methods;

    /**
     * Constructor.
     *
     * @param string                                               $tag         Hook tag.
     * @param Closure|string|int|array{0: class-string,1: string}  $priority    Hook priority.
     * @param int                                                  $context     Hook context.
     * @param null|Closure|string|array{0: class-string,1: string} $conditional Conditional callback.
     * @param array<int,string>|string|false                       $modifiers   Values to replace in the tag name.
     * @param int                                                  $invoke      The invocation strategy.
     * @param int|null                                             $args        The number of arguments to pass to the callback.
     * @param array<string>                                        $params      The parameters to pass to the callback.
     */
    public function __construct(
        string $tag,
        Closure|array|int|string $priority = 10,
        int $context = self::CTX_GLOBAL,
        array|string|\Closure|null $conditional = null,
        string|array|bool $modifiers = false,
        protected int $invoke = self::INV_STANDARD,
        protected ?int $args = null,
        protected array $params = array(),
    ) {
        parent::__construct( $tag, $priority, $context, $conditional, $modifiers );
    }

    /**
     * Get compiler data for this callback.
     *
     * @internal Hook parser/compiler detail.
     *
     * @return array<string,mixed>
     */
    public function get_data(): array {
        $data                     = parent::get_data();
        $data['params']['method'] = $this->method;
        return $data;
    }

    /**
     * Export unbound attribute metadata.
     *
     * @internal Discovery metadata detail.
     * @return array<string,mixed>
     */
    public function get_declaration(): array {
        return parent::get_declaration() + array(
            'args'   => $this->args,
            'invoke' => $this->invoke,
            'params' => $this->params,
        );
    }
}
