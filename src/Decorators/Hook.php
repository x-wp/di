<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
/**
 * Hook class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Automattic\Jetpack\Constants;
use Closure;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use Reflector;
use XWP\DI\Container;
use XWP\DI\Core\Wrapper;
use XWP\DI\Interfaces\Decorates_Hook;
use XWP\DI\Traits\Hook_Invoke_Methods;
use XWP\DI\Utils\Reflection;
use XWP_Context;

/**
 * Base hook from which the action and filter decorators inherit.
 *
 * @template THndlr of object
 * @template TRflct of ReflectionClass<THndlr>|ReflectionMethod
 * @implements Decorates_Hook<THndlr,TRflct>
 */
abstract class Hook implements Decorates_Hook {
    /**
     * The classname of the handler.
     *
     * @var class-string<THndlr>
     */
    protected string $classname;

    /**
     * Reflector instance.
     *
     * @var TRflct
     */
    protected ReflectionClass|ReflectionMethod $reflector;

    /**
     * Constructor.
     *
     * @param string|null                                             $tag         Hook tag.
     * @param null|Closure|string|int|array{0:class-string,1: string} $priority    Hook priority.
     * @param int                                                     $context     Hook context.
     * @param array<int,string>|string|false                          $modifiers   Values to replace in the tag name.
     * @param bool                                                    $debug       Debug this hook.
     * @param bool                                                    $trace       Trace this hook.
     * @param string|null                                             $token       Unique ID for the hook.
     */
    public function __construct(
        protected ?string $tag = null,
        protected array|int|string|Closure|null $priority = null,
        protected int $context = self::CTX_GLOBAL,
        protected string|array|bool $modifiers = false,
        protected ?bool $debug = null,
        protected ?bool $trace = null,
        protected ?string $token = null,
    ) {
        $this->tag ??= '';
    }

    /**
     * Getter for protected properties.
     *
     * @param  string $name Property name.
     * @return mixed
     */
    public function __get( string $name ): mixed {
        return \method_exists( $this, "get_{$name}" )
            ? $this->{"get_{$name}"}()
            : $this->$name ?? null;
    }

    public function with_classname( string $classname ): static {
        $this->classname = $classname;

        return $this;
    }

    /**
     * Set the reflector instance.
     *
     * @param  TRflct $r Reflector instance.
     * @return static
     */
    public function with_reflector( \Reflector $r ): static {
        $this->reflector ??= $r;

        return $this;
    }

    public function get_classname(): string {
        return $this->classname;
    }

    public function get_context(): int {
        return $this->context;
    }

    public function get_data(): array {
        return array(
            'construct' => \array_combine(
                $this->get_constructor_args(),
                \array_map(
                    fn( $arg ) => $this->$arg,
                    $this->get_constructor_args(),
                ),
            ),
            'hydrate'   => $this->is_global() && $this->is_unconditional(),
            'metatype'  => $this->get_metatype(),
        );
    }

    final public function get_token(): string {
        return $this->token ??= $this->get_name(); // \uniqid( $this->get_token_prefix() );
    }

    final public function get_name(): string {
        return $this->generate_token();
    }

    /**
     * Get the token base.
     *
     * @return string
     */
    protected function get_token_base(): string {
        return $this->get_classname();
    }

    abstract protected function get_token_prefix(): string;

    /**
     * Get the token suffix.
     *
     * @return string
     */
    protected function get_token_suffix(): string {
        return '';
    }

    /**
     * Get the constructor keys.
     *
     * @return array<string>
     */
    protected function get_constructor_args(): array {
        return array(
            'tag',
            'priority',
            'context',
            'modifiers',
            'classname',
            'debug',
            'trace',
            'id',
        );
    }

    protected function get_metatype(): string {
        return \str_replace( 'Decorators', 'Core', static::class );
    }

    protected function is_global(): bool {
        return self::CTX_GLOBAL === $this->context;
    }

    protected function is_unconditional(): bool {
        return ! \method_exists( $this->classname, 'can_initialize' );
    }

    /**
     * Generate the injection token.
     *
     * @return string
     */
    private function generate_token(): string {
        $suffix = \ltrim( $this->get_token_suffix(), '-\\' );
        $base   = \trim( $this->get_token_base(), '-\\' );

        return \trim( \XWP_DI_TOKEN_PREFIX . "{$base}::{$suffix}", '-:/' );
    }
}
