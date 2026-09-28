<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, Universal.Operators.DisallowShortTernary.Found
/**
 * CLI callback runtime.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use Closure;
use WP_CLI;
use XWP\DI\Container;
use XWP\DI\Definition\CallbackDefinition;
use XWP\DI\Interfaces\Can_Invoke;

use function WP_CLI\Utils\get_flag_value;

/**
 * Registers commands and resolves command arguments through the container.
 *
 * @template T of object
 * @template H of \XWP\DI\Interfaces\Can_Handle_CLI<T>
 * @extends Callback<T,H>
 * @internal
 */
class CLI_Callback extends Callback {
    /**
     * Subcommand name.
     *
     * @var string
     */
    protected string $subcommand;

    /**
     * Command argument specifications, resolved during registration.
     *
     * @var array<mixed>
     */
    protected array $cmd_args;

    /**
     * Short command description.
     *
     * @var string
     */
    protected string $summary;

    /**
     * Long command description.
     *
     * @var string|array<string|array<string>>
     */
    protected string|array $description;

    /**
     * Command execution hook.
     *
     * @var string|null
     */
    protected ?string $when;
    /**
     * Deferred registration setting.
     *
     * @var bool|null
     */
    protected ?bool $deferred;

    /**
     * Before invocation callback.
     *
     * @var null|Closure|string|array{0:class-string,1:string}
     */
    protected null|Closure|string|array $before;

    /**
     * After invocation callback.
     *
     * @var null|Closure|string|array{0:class-string,1:string}
     */
    protected null|Closure|string|array $after;

    /**
     * Constructor.
     *
     * @param CallbackDefinition $definition Callback metadata.
     * @param Container          $container Runtime container.
     */
    public function __construct( CallbackDefinition $definition, Container $container ) {
        parent::__construct( $definition, $container );
        $options           = $definition->get_options();
        $this->subcommand  = $options['command'];
        $this->cmd_args    = $options['args'];
        $this->summary     = $options['summary'];
        $this->description = $options['description'];
        $this->when        = $options['when'];
        $this->deferred    = $options['deferred'];
        $this->before      = $options['before'];
        $this->after       = $options['after'];
    }

    /**
     * Get the before-invoke callback.
     *
     * @internal Runtime command registration detail.
     *
     * @return ?Closure
     */
    public function get_before_invoke(): ?Closure {
        return $this->get_invoke( $this->before );
    }

    /**
     * Get the after-invoke callback.
     *
     * @internal Runtime command registration detail.
     *
     * @return ?Closure
     */
    public function get_after_invoke(): ?Closure {
        return $this->get_invoke( $this->after );
    }

    public function get_command(): string {
        return \sprintf( '%s %s', $this->get_handler()->get_namespace(), $this->get_subcommand() );
    }

    public function get_subcommand(): string {
        return $this->subcommand;
    }

    public function get_longdesc(): ?string {
        $desc = array();

        foreach ( (array) $this->description as $line ) {
            $desc[] = \is_array( $line ) ? \implode( "\n", $line ) : $line;
            $desc[] = '';
        }

        if ( $desc ) {
            \array_unshift( $desc, "## EXAMPLES \n" );
        }

        return \implode( "\n", $desc ) ?: null;
    }

    public function get_priority(): int {
        return $this->get_handler()->get_priority();
    }

    public function get_shortdesc(): ?string {
        return $this->summary ?: null;
    }

    /**
     * Run the command through the container.
     *
     * @internal Runtime command dispatch detail.
     *
     * @param  array<int,mixed>    $positional Positional arguments.
     * @param  array<string,mixed> $assoc      Associative arguments.
     */
    public function run_cmd( array $positional, array $assoc ): void {
        $args = \array_merge(
            $this->format_pos_args( $positional ),
            array( 'flags' => $this->format_flag_args( $assoc ) ),
            \array_map( array( $this, 'get_cb_arg' ), $this->definition->get_params() ),
        );

        $this->get_container()->call( array( $this->get_handler()->get_target(), $this->get_method() ), $args );
    }

    /**
     * Get the before/after invoke callback.
     *
     * @param  null|Closure|string|array{0:class-string,1:string} $cb Callback.
     * @return null|Closure
     */
    protected function get_invoke( null|Closure|string|array $cb ): ?Closure {
        if ( null !== $cb ) {
            return fn() => $this->get_container()->call( $cb );
        }

        return null;
    }

    /**
     * Get the command arguments.
     *
     * @return array<string,mixed>
     */
    protected function get_hook_args(): array {
        $args = array(
            'after_invoke'  => $this->get_after_invoke(),
            'before_invoke' => $this->get_before_invoke(),
            'is_deferred'   => $this->deferred,
            'longdesc'      => $this->get_longdesc(),
            'shortdesc'     => $this->get_shortdesc(),
            'synopsis'      => $this->cmd_args,
            'when'          => $this->when,
        );

        return \array_filter( $args, static fn( $v ) => null !== $v );
    }

    protected function load_hook( ?string $tag = null ): bool {
        WP_CLI::add_command(
            $this->get_command(),
            $this->get_callback(),
            $this->parse_cmd_args()->get_hook_args(),
        );

        return true;
    }

    /**
     * Get the route callback.
     *
     * @return array{0:T, 1: string}|Closure
     */
    protected function get_callback(): array|Closure {
        return $this->cb_valid( Can_Invoke::INV_STANDARD )
            ? array( $this->get_handler()->get_target(), $this->get_method() )
            : array( $this, 'run_cmd' );
    }

    /**
     * Get the command arguments.
     */
    protected function parse_cmd_args(): static {
        foreach ( $this->cmd_args as &$arg ) {
            if ( ! isset( $arg['options'] ) ) {
                continue;
            }

            $arg['options'] = $this->get_arg_opts( $arg['options'] );
        }

        return $this;
    }

    /**
     * Get the Container callback.
     *
     * @param  array<string> $raw Container options.
     * @return array<mixed>
     */
    protected function get_arg_opts( array $raw ): mixed {
        $cb = \current( $raw );

        if ( \str_contains( $cb, '::' ) ) {
            return $this->get_container()->call( $cb, \array_slice( $raw, 1 ) );
        }

        $opts = array();

        foreach ( $raw as $opt ) {
            $opt = $this->get_container()->has( $opt )
                ? $this->get_container()->get( $opt )
                : $opt;

            $opts = \array_merge( $opts, \xwp_str_to_arr( $opt ) );
        }

        return \array_values( \array_unique( $opts ) );
    }

    /**
     * Format the positional arguments.
     *
     * @param  array<int,mixed> $args Positional arguments.
     * @return array<string,mixed>
     */
    protected function format_pos_args( array $args ): array {
        $pos  = \array_values( \wp_list_filter( $this->cmd_args, array( 'type' => 'positional' ) ) );
        $fmtd = array();

        foreach ( $pos as $i => $arg ) {
            $val = $arg['repeating'] ?? false
                ? \array_slice( $args, $i )
                : $args[ $i ] ?? null;

            if ( isset( $arg['format'] ) ) {
                $val = $this->get_container()->call( $arg['format'], array( $val ) );
            }

            $fmtd[ $arg['var'] ?? $arg['name'] ] = $val;
        }

        return $fmtd;
    }

    /**
     * Format the flag arguments.
     *
     * @param  array<string,mixed> $flags Flag arguments.
     * @return array<string,mixed>
     */
    protected function format_flag_args( array $flags ): array {
        $flg  = \array_values( \wp_list_filter( $this->cmd_args, array( 'type' => 'positional' ), 'NOT' ) );
        $fmtd = array();

        foreach ( $flg as $flag ) {
            if ( 'assoc' !== $flag['type'] ) {
                $fmtd[ $flag['name'] ] = get_flag_value( $flags, $flag['name'], $flag['default'] ?? false );
                continue;
            }

            $val = $flags[ $flag['name'] ] ?? $flag['default'] ?? null;
            $val = ( $flag['format'] ?? static fn( $v ) => $v )( $val );

            $fmtd[ $flag['name'] ] = $val;
        }

        return $fmtd;
    }/**
     * Add the command arguments to the command.
     *
     * Set the summary in the `CLI_Command` decorator to override this description
     *
     * @param  array<int,mixed>    $positional Positional arguments.
     * @param  array<string,mixed> $assoc      Associative arguments.
     */
}
