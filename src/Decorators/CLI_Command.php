<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, Universal.Operators.DisallowShortTernary.Found

namespace XWP\DI\Decorators;

use Closure;
use WP_CLI;
use XWP\DI\Interfaces\Can_Handle;

use function WP_CLI\Utils\get_flag_value;

/**
 * Decorator for defining CLI commands.
 *
 * @template T of object
 * @extends Action<T,CLI_Handler<T>>
 *
 * @property-read string                         $command       Command name.
 * @property-read string                         $shortdesc     Command short description.
 * @property-read string                         $longdesc      Command long description.
 * @property-read array{0:T, 1: string}|Closure  $callback      Command callback.
 * @property-read null|Closure                   $before_invoke Before invoke callback.
 * @property-read null|Closure                   $after_invoke  After invoke callback.
 * @property-read array<int,array<string,mixed>> $hook_args      Command arguments.
 */
#[\Attribute( \Attribute::TARGET_METHOD )]
class CLI_Command extends Action {
    protected const ARG_TYPE = array( 'positional', 'assoc', 'flag' );

    /**
     * Subcommand.
     *
     * @var string
     */
    protected string $subcommand;


    /**
     * Command arguments.
     *
     * @var array<mixed>
     */
    protected array $cmd_args;

    /**
     * Undocumented function
     *
     * @param string                                             $command       Command name.
     * @param array<mixed>                                       $args          Command arguments.
     * @param array<string,string>                               $params        Injection parameters.
     * @param string                                             $summary       Short description.
     * @param string|array<string|array<string>>                 $description   Long description.
     * @param string|null                                        $when          When to invoke the command.
     * @param bool|null                                          $deferred      Whether to defer adding the command.
     * @param null|Closure|string|array{0:class-string,1:string} $before Function to call before invoking the command.
     * @param null|Closure|string|array{0:class-string,1:string} $after  Function to call after invoking the command.
     */
    public function __construct(
        string $command,
        array $args = array(),
        array $params = array(),
        protected string $summary = '',
        protected string|array $description = array(),
        protected ?string $when = null,
        protected ?bool $deferred = null,
        protected null|Closure|string|array $before = null,
        protected null|Closure|string|array $after = null,
    ) {
        $this->subcommand = $command;
        $this->cmd_args   = $args;

        parent::__construct(
            tag: 'cli_init',
            context: self::CTX_CLI,
            invoke: self::INV_PROXIED,
            params: $params,
        );
    }

    /**
     * Get the before/after invoke callback.
     *
     * @param  null|Closure|string|array{0:class-string,1:string} $cb Callback.
     * @return null|Closure
     */
    protected function get_invoke( null|Closure|string|array $cb ): null|Closure {
        if ( null !== $cb ) {
            return fn() => $this->container->call( $cb );
        }

        return null;
    }

    protected function get_before_invoke(): null|Closure {
        return $this->get_invoke( $this->before );
    }

    protected function get_after_invoke(): null|Closure {
        return $this->get_invoke( $this->after );
    }

    protected function get_priority(): int {
        return $this->handler->priority;
    }

    protected function get_command(): string {
        return \sprintf( '%s %s', $this->handler->namespace, $this->subcommand );
    }

    protected function get_shortdesc(): ?string {
        return $this->summary ?: null;
    }

    protected function get_longdesc(): ?string {
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

    /**
     * Get the command arguments.
     *
     * @return array<string,mixed>
     */
    protected function get_hook_args(): array {
        $args = array(
            'after_invoke'  => $this->after_invoke,
            'before_invoke' => $this->before_invoke,
            'is_deferred'   => $this->deferred,
            'longdesc'      => $this->longdesc,
            'shortdesc'     => $this->shortdesc,
            'synopsis'      => $this->cmd_args,
            'when'          => $this->when,
        );

        return \array_filter( $args, static fn( $v ) => null !== $v );
    }

    protected function load_hook( ?string $tag = null ): bool {
        WP_CLI::add_command( $this->command, $this->callback, $this->hook_args );

        return true;
    }

    /**
     * Get the route callback.
     *
     * @return array{0:T, 1: string}|Closure
     */
    protected function get_callback(): array|Closure {
        return $this->cb_valid( self::INV_STANDARD )
            ? array( $this->handler->target, $this->method )
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
            return $this->container->call( $cb, \array_slice( $raw, 1 ) );
        }

        $opts = array();

        foreach ( $raw as $opt ) {
            $opt = $this->container->has( $opt )
                ? $this->container->get( $opt )
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
                $val = $this->container->call( $arg['format'], array( $val ) );
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
    }

    public function with_handler( Can_Handle $handler ): static {
        return parent::with_handler( $handler )->parse_cmd_args();
    }

    /**
     * Add the command arguments to the command.
     *
     * Set the summary in the `CLI_Command` decorator to override this description
     *
     * @param  array<int,mixed>    $positional Positional arguments.
     * @param  array<string,mixed> $assoc      Associative arguments.
     */
    public function run_cmd( array $positional, array $assoc ): void {
        $args = \array_merge(
            $this->format_pos_args( $positional ),
            array( 'flags' => $this->format_flag_args( $assoc ) ),
            \array_map( array( $this, 'get_cb_arg' ), $this->params ),
        );

        $this->container->call( array( $this->handler->target, $this->method ), $args );
    }
}
