<?php //phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound, Squiz.Commenting.FunctionComment.Missing

namespace XWP\DI\Decorators;

use cli\progress\Bar;
use Closure;
use WP_CLI;
use XWP\DI\Interfaces\Can_Handle_CLI;
use XWP_CLI_Namespace as NSC;

use function WP_CLI\Utils\make_progress_bar;

/**
 * Decorator for CLI commands.
 *
 * @template T of object
 * @extends Handler<T>
 * @implements Can_Handle_CLI<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class CLI_Handler extends Handler implements Can_Handle_CLI {
    /**
     * Array of root commands.
     *
     * @var array<string,bool>
     */
    protected static array $roots = array();

    /**
     * Progress bar.
     *
     * @var ?Bar
     */
    protected static ?Bar $bar;

    /**
     * Progress message.
     *
     * @var string
     */
    protected static string $message;

    /**
     * Progress count.
     *
     * @var int
     */
    protected static int $count;

    /**
     * Progress interval.
     *
     * @var int
     */
    protected static int $interval;

    /**
     * Current progress.
     *
     * @var int
     */
    protected static int $current;

    /**
     * Ask for user input.
     *
     * @param  string        $question Question to ask.
     * @param  array<string> $choices  Array of choices.
     * @param  string|null   $def  Default choice.
     * @return string
     */
    public static function choice( string $question, array $choices, ?string $def = null ): string {
        $lines   = array( $question );
        $choices = \array_values( $choices );
        $length  = \strlen( (string) \count( $choices ) ) + 2;

        foreach ( $choices as $i => $c ) {
            $lines[] = \sprintf( '%s: %s', \str_pad( (string) ( $i + 1 ), $length, ' ', STR_PAD_LEFT ), $c );
        }

        $choice = \trim( self::prompt( \implode( \PHP_EOL, $lines ), false ) );

        if ( ! \preg_match( '/^\d+$/', $choice ) || ! isset( $choices[ \intval( $choice ) - 1 ] ) ) {
            WP_CLI::error( 'Invalid choice.' );
        }

        $choice = (int) $choice - 1;

        return $choices[ $choice ] ?? $def ?? '';
    }

    /**
     * Prompt for user input.
     *
     * @param  string $question  Question to ask.
     * @param  bool   $multiline Whether to allow multiline input.
     * @return string
     */
    public static function prompt( string $question, bool $multiline = false ): string {
        if ( $multiline ) {
            $question .= ' (Press Ctrl+D to finish)';
        }

        WP_CLI::line( $question );

        $loop  = true;
        $stdin = \fopen( 'php://stdin', 'r' );
        $input = '';

        do {
            $input .= \fgets( $stdin );
            $loop   = $multiline && ! \feof( $stdin );

        } while ( $loop );

        //phpcs:ignore
        \fclose( $stdin );

        return \rtrim( $input );
    }

    /**
     * Track progress.
     *
     * @param  string           $message Message to display.
     * @param  array<mixed>|int $count  Array of items or count of items.
     * @param  int              $interval Interval to update the progress bar.
     * @param  string|null      $action Action to hook into to update the progress bar.
     */
    public static function track( string $message, array|int $count, int $interval = 100, ?string $action = null, ): void {
        static::$message  = $message . ' (%d / %d)';
        static::$count    = \is_array( $count ) ? \count( $count ) : $count;
        static::$interval = $interval;
        static::$current  = 0;
        static::$bar      = make_progress_bar( static::$message, static::$count, static::$interval );

        if ( ! $action ) {
            return;
        }

        \add_action( $action, array( static::class, 'tick' ), 10, 0 );
    }

    /**
     * Update the progress bar.
     *
     * @param  int         $incr    Increment.
     * @param  string|null $message Message to display.
     */
    public static function tick( int $incr = 1, ?string $message = null ): void {
        $message ??= static::$message;
        $message   = \sprintf( $message, ( static::$current += $incr ), static::$count );

        static::$bar->tick( $incr, $message );
    }

    /**
     * Finish the progress bar.
     */
    public static function finish(): void {
        static::$bar->finish();

        static::$message  = '';
        static::$count    = 0;
        static::$interval = 0;
        static::$current  = 0;
    }

    /**
     * Constructor.
     *
     * @param string                                        $namespace   Command namespace.
     * @param string                                        $description Command description.
     * @param Closure|string|int|array{class-string,string} $priority    Hook priority.
     * @param mixed                                         ...$args     Additional arguments.
     */
    public function __construct(
        protected string $namespace,
        protected string $description = '',
        Closure|string|int|array $priority = 10,
        mixed ...$args,
    ) {
        parent::__construct( tag: 'cli_init', priority: $priority, context: static::CTX_CLI );
    }

    public function get_namespace(): string {
        return $this->namespace;
    }

    protected function add_command(): bool {
        return WP_CLI::add_command( $this->namespace, NSC::class, array( 'shortdesc' => $this->description ) );
    }

    public function load( array $args = array() ): bool {
        static::$roots[ $this->namespace ] ??= $this->add_command();

        return parent::load();
    }

    protected function get_constructor_args(): array {
        return \array_merge(
            parent::get_constructor_args(),
            array( 'namespace', 'description' ),
        );
    }
}
