<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh, SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
/**
 * Filter decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;

/**
 * Filter hook decorator.
 *
 * @template T of object
 * @template H of Can_Handle<T>
 * @extends Hook<T,\ReflectionMethod>
 */
#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Filter extends Hook {
    /**
     * Standard invocation.
     */
    public const INV_STANDARD = Can_Invoke::INV_STANDARD;

    /**
     * Invocation through the container.
     */
    public const INV_PROXIED = Can_Invoke::INV_PROXIED;

    /**
     * Invoke only once.
     */
    public const INV_ONCE = Can_Invoke::INV_ONCE;

    /**
     * Prevent recursive invocation.
     */
    public const INV_LOOPED = Can_Invoke::INV_LOOPED;

    /**
     * Protect against fatal errors during invocation.
     */
    public const INV_SAFELY = Can_Invoke::INV_SAFELY;

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
