<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found, Squiz.Commenting.FunctionComment.Missing
/**
 * Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use ReflectionClass;
use Reflector;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Utils\Reflection;

/**
 * Decorator for handling WordPress hooks.
 *
 * @template T of object
 *
 * @extends Hook<T,ReflectionClass<T>>
 * @implements Can_Handle<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Handler extends Hook implements Can_Handle {
    /**
     * Legacy custom-subclass and typed-view compatibility.
     *
     * @use \XWP\DI\Compatibility\Handler_Methods<T>
     */
    use \XWP\DI\Compatibility\Handler_Methods;

    /**
     * Constructor.
     *
     * @param string                                             $tag         Hook tag.
     * @param Closure|string|int|array{0:class-string,1:string}  $priority    Hook priority.
     * @param int                                                $context     Hook context.
     * @param null|Closure|string|array{0:class-string,1:string} $conditional Conditional callback.
     * @param array<int,string>|string|false                     $modifiers   Values to replace in the tag name.
     * @param string                                             $strategy    Initialization strategy.
     * @param bool                                               $hookable    Is the handler hookable.
     * @param mixed                                              ...$args     Additional arguments.
     */
    public function __construct(
        ?string $tag = null,
        Closure|string|int|array $priority = 10,
        int $context = self::CTX_GLOBAL,
        array|string|Closure|null $conditional = null,
        string|array|false $modifiers = false,
        string $strategy = self::INIT_AUTO,
        ?bool $hookable = null,
        mixed ...$args,
    ) {
        $this->strategy    = $strategy;
        $this->loaded      = self::INIT_USER === $strategy;
        $this->hookable    = $hookable;
        $this->compat_args = \array_keys( \array_filter( $args ) );

        parent::__construct( $tag, $tag ? $priority : null, $context, $conditional, $modifiers );
    }

    /**
     * Get compiler data for this handler.
     *
     * @internal Hook parser/compiler detail.
     *
     * @return array{
     *   args: array<string,mixed>,
     *   type: class-string<static>,
     *   params: array{classname: class-string<T>},
     * }
     */
    public function get_data(): array {
        $data = parent::get_data();

        $data['params']['callbacks'] = $this->get_callbacks();
        $data['params']['params']    = \array_combine(
            \array_keys( $this->params ),
            \array_map(
                fn( string $m ) => $this->get_params( $m )?->get( $this ) ?? array(),
                \array_keys( $this->params ),
            ),
        );

        return $data;
    }

    /**
     * Export unbound attribute metadata.
     *
     * @internal Discovery metadata detail.
     * @return array<string,mixed>
     */
    public function get_declaration(): array {
        $args             = parent::get_declaration();
        $args['hookable'] = $this->hookable;
        $args['strategy'] = $this->strategy;
        $args['priority'] = $this->prio ?? 10;
        return $args;
    }
}
