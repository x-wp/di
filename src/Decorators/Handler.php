<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found, Squiz.Commenting.FunctionComment.Missing
/**
 * Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use XWP\DI\Interfaces\Can_Handle;

/**
 * Decorator for handling WordPress hooks.
 *
 * @template T of object
 *
 * @extends Hook<T,\ReflectionClass<T>>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Handler extends Hook {
    /**
     * Initialize the handler early.
     */
    public const INIT_EARLY = Can_Handle::INIT_EARLY;

    /**
     * Initialize the handler immediately.
     */
    public const INIT_NOW = Can_Handle::INIT_NOW;

    /**
     * Initialize the handler on demand.
     */
    public const INIT_LAZY = Can_Handle::INIT_LAZY;

    /**
     * Initialize the handler just in time.
     */
    public const INIT_JIT = Can_Handle::INIT_JIT;

    /**
     * Initialize the handler automatically.
     */
    public const INIT_AUTO = Can_Handle::INIT_AUTO;

    /**
     * Initialize the handler dynamically.
     */
    public const INIT_USER = Can_Handle::INIT_USER;

    /**
     * Initialize the handler immediately.
     *
     * @deprecated Use INIT_NOW instead.
     */
    public const INIT_IMMEDIATELY = Can_Handle::INIT_NOW;

    /**
     * Initialize the handler on demand.
     *
     * @deprecated Use INIT_LAZY instead.
     */
    public const INIT_ON_DEMAND = Can_Handle::INIT_LAZY;

    /**
     * Initialize the handler just in time.
     *
     * @deprecated Use INIT_JIT instead.
     */
    public const INIT_JUST_IN_TIME = Can_Handle::INIT_JIT;

    /**
     * Initialize the handler dynamically.
     *
     * @deprecated Use INIT_USER instead.
     */
    public const INIT_DYNAMICALY = Can_Handle::INIT_USER;

    /**
     * Initialize the handler automatically.
     *
     * @deprecated Use INIT_AUTO instead.
     */
    public const INIT_DEFFERED = Can_Handle::INIT_AUTO;

    /**
     * Declared initialization strategy.
     *
     * @var string
     */
    protected string $strategy;

    /**
     * Whether to discover callbacks automatically.
     *
     * @var bool|null
     */
    protected ?bool $hookable;

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
     *
     * @phpstan-ignore constructor.unusedParameter (Preserve deprecated constructor arguments.)
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
        $this->strategy = $strategy;
        $this->hookable = $hookable;

        parent::__construct( $tag, $tag ? $priority : null, $context, $conditional, $modifiers );
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
