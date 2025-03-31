<?php //phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DevelopmentFunctions.error_log_var_export
/**
 * Finally, a light, permissions-checking logging class.
 *
 * Originally written for use with wpSearch
 *
 * Usage:
 * $log = new Katzgrau\KLogger\Logger('/var/log/', Psr\Log\LogLevel::INFO);
 * $log->info('Returned a million search results'); //Prints to the log file
 * $log->error('Oh dear.'); //Prints to the log file
 * $log->debug('x = 5'); //Prints nothing due to current severity threshhold
 *
 * @author  Kenny Katzgrau <katzgrau@gmail.com>
 * @since   July 26, 2008
 * @link    https://github.com/katzgrau/KLogger
 * @version 1.0.0
 */

namespace XWP\DI;

use DateTime;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use RuntimeException;
use Stringable;

/**
 * Class documentation
 */
class Logger extends AbstractLogger {
    /**
     * Instances of the Logger
     *
     * @var array<string,array<string,self|Logger>>
     */
    protected static array $instances = array();

    /**
     * Holds the directory in which the log file will be stored
     *
     * @var array<string,resource>
     */
    protected static array $handles = array();

    /**
     * KLogger options
     *  Anything options not considered 'core' to the logging library should be
     *  settable view the third parameter in the constructor
     *
     *  Core options include the log file path and the log threshold
     *
     * @var array{
     *   filename: ?string,
     *   date_format: string,
     *   max_lines: false|int,
     *   log_format: false|string,
     * }
     */
    protected array $options = array(
        'date_format' => 'G:i:s',
        'filename'    => null,
        'log_format'  => false,
        'max_lines'   => false,
    );

    /**
     * The number of lines logged in this instance's lifetime
     *
     * @var int
     */
    private $line_count = 0;

    /**
     * Log Levels
     *
     * @var array<string,int>
     */
    protected $levels = array(
        LogLevel::ALERT     => 1,
        LogLevel::CRITICAL  => 2,
        LogLevel::DEBUG     => 7,
        LogLevel::EMERGENCY => 0,
        LogLevel::ERROR     => 3,
        LogLevel::INFO      => 6,
        LogLevel::NOTICE    => 5,
        LogLevel::WARNING   => 4,
    );

    /**
     * This holds the last line logged to the logger
     *  Used for unit tests
     *
     * @var string
     */
    private $last_line = '';

    /**
     * Get an instance of the Logger for the specified app_id and context.
     *
     * @param  string $app_id  Application ID.
     * @param  string $context The context.
     * @return self|null
     */
    public static function instance( string $app_id, string $context = 'XWP\\App' ): ?self {
        return self::$instances[ $app_id ][ self::format_context( $context ) ] ?? null;
    }

    /**
     * Formats the context for logging.
     *
     * @param  string $context The context.
     * @return string
     */
    public static function format_context( string $context ): string {
        if ( ! \class_exists( $context ) ) {
            return \ucfirst( \strtolower( $context ) );
        }

        $context = \explode( '/', \str_replace( '\\', '/', $context ) );
        return \implode( '\\', \array_slice( $context, -3, 3 ) );
    }

    /**
     * Constructor
     *
     * @param  string              $app_id  Application ID.
     * @param  string              $basedir File path to the logging directory.
     * @param  string              $level   Log level threshold.
     * @param  string              $context The context.
     * @param  array<string,mixed> $options Extra options to set.
     */
    public function __construct(
        protected string $app_id,
        protected string $basedir = \WP_CONTENT_DIR . 'xwp-di',
        protected string $level = LogLevel::INFO,
        protected string $context = 'XWP\\App',
        array $options = array(),
    ) {
        $this
            ->set_app( $app_id )
            ->set_options( $options )
            ->set_basedir( $basedir )
            ->set_handle( 'a' )
            ->set_instance( $this );
    }

    /**
     * Class destructor
     */
    public function __destruct() {
        if ( ! $this->get_handle() ) {
            return;
        }

        \fclose( $this->get_handle() );

        self::$handles[ $this->app_id ] = false;
    }

    /**
     * Output debug info
     *
     * @return array<string,mixed>
     */
    public function __debugInfo() {
        return array(
            'handles'   => self::$handles,
            'instances' => self::$instances,
        );
    }

    /**
     * Get the app ID
     *
     * @return string
     */
    public function get_app_id(): string {
        return $this->app_id;
    }

    /**
     * Get the context
     *
     * @return string
     */
    public function get_context(): string {
        return $this->format_context( $this->context );
    }

    /**
     * Get the file path that the log is currently writing to
     *
     * @return string
     */
    public function get_filename(): string {
        return \sprintf( '%s/%s', $this->basedir, $this->options['filename'] );
    }

    /**
     * Get the last line logged to the log file
     *
     * @return string
     */
    public function get_last_line(): string {
        return $this->last_line;
    }

    /**
     * Returns a new instance with the given context
     *
     * @param  string $context The context.
     * @return self
     */
    public function with_context( string $context ): self {
        return $this->get_instance( $context ) ?? $this->create_instance( $context );
    }

    /**
     * Sets the context for this instance
     *
     * @param  string $context The context.
     * @return self
     */
    public function set_context( string $context ): self {
        $this->context = $context;

        return $this;
    }

    /**
     * Sets the file path for this instance
     *
     * @param  string|null $basedir File path to the logging directory.
     * @return self
     *
     * @throws \RuntimeException If the directory can't be created.
     */
    public function set_basedir( ?string $basedir = null ): self {
        $basedir ??= \WP_CONTENT_DIR . '/logs/xwp-di';

        if ( ! \wp_mkdir_p( $basedir ) ) {
            throw new \RuntimeException(
                'The directory could not be created. Check that appropriate permissions have been set.',
            );
        }

        $this->basedir = $basedir;

        self::$handles[ $this->app_id ] ??= null;

        return $this;
    }

    /**
     * Sets the file handle for this instance
     *
     * @param string $mode File mode.
     * @return self
     *
     * @throws \RuntimeException If the file could not be opened.
     */
    public function set_handle( string $mode ): self {
        if ( \is_resource( $this->get_handle() ) ) {
            return $this;
        }

        self::$handles[ $this->get_app_id() ] = \fopen( $this->get_filename(), $mode );

        if ( false === $this->get_handle() ) {
            throw new \RuntimeException(
                'The file could not be opened. Check that appropriate permissions have been set.',
            );
        }

        return $this;
    }

    /**
     * Set the options.
     *
     * @param  array<string,mixed> $options Options to set.
     * @return self
     */
    public function set_options( array $options ): self {
        $defaults = array(
            'date_format' => 'G:i:s',
            'filename'    => \sprintf( '%s-%s.log', \rtrim( $this->app_id, '_-' ), \gmdate( 'Y-m-d' ) ),
            'log_format'  => false,
            'max_lines'   => false,
        );

        $this->options = \xwp_parse_args( $options, $defaults );

        return $this;
    }

    /**
     * Sets the date format used by all instances of KLogger
     *
     * @param string $format Valid format string for date.
     */
    public function set_date_format( string $format ): void {
        $this->options['date_format'] = $format;
    }

    /**
     * Sets the Log Level Threshold
     *
     * @param string $level The log level threshold.
     */
    public function set_level( string $level ): void {
        $this->level = $level;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed               $level The log level.
     * @param string|Stringable   $message The message to log.
     * @param  array<string,mixed> $context The context.
     */
    public function log( $level, string|Stringable $message, array $context = array() ): void {
        if ( $this->levels[ $this->level ] < $this->levels[ $level ] ) {
            return;
        }

        $message = $this->format_message( $level, $message, $context );
        $this->write( $message );
    }

    /**
     * Writes a line to the log without prepending a status or timestamp
     *
     * @param string|Stringable $message Line to write to the log.
     *
     * @throws \RuntimeException If the file could not be written to.
     */
    public function write( string|Stringable $message ): void {
        if ( ! $this->get_handle() ) {
            return;
        }

        if ( false === \fwrite( $this->get_handle(), $message ) ) {
            throw new \RuntimeException(
                'The file could not be written to. Check that appropriate permissions have been set.',
            );
        }

        $this->last_line = \trim( $message );
        ++$this->line_count;

        if ( ! $this->options['max_lines'] || 0 !== $this->line_count % $this->options['max_lines'] ) {
            return;
        }

        \fflush( $this->get_handle() );
    }

    /**
     * Get the instance for the given context
     *
     * @param  string $context The context.
     * @return self|null
     */
    protected function get_instance( string $context ): ?self {
        return self::instance( $this->get_app_id(), $context );
    }

    /**
     * Get the handle for the log file
     *
     * @return resource|false
     */
    protected function get_handle(): mixed {
        return self::$handles[ $this->app_id ] ?? false;
    }

    /**
     * Sets the app ID for this instance
     *
     * @param  string $app_id The app ID.
     * @return self
     */
    protected function set_app( string $app_id ): self {
        self::$instances[ $app_id ] ??= array();
        $this->app_id                 = $app_id;

        return $this;
    }

    /**
     * Sets the instance for the given context
     *
     * @param  Logger $ins The instance.
     * @return Logger
     */
    protected function set_instance( Logger $ins ): Logger {
        return self::$instances[ $ins->get_app_id() ][ $ins->get_context() ] ??= $ins;
    }

    /**
     * Creates a new instance with the given context
     *
     * @param  string $context The context.
     * @return Logger
     */
    protected function create_instance( string $context ): Logger {
        $logger = ( clone $this )->set_context( $context );

        return $this->set_instance( $logger );
    }

    /**
     * Formats the message for logging.
     *
     * @param  string              $level   The Log Level of the message.
     * @param  string|Stringable   $message The message to log.
     * @param  array<string,mixed> $context The context.
     * @return string
     */
    protected function format_message( string $level, string|Stringable $message, array $context ): string {
        $message = $this->options['log_format']
            ? $this->custom_format( $level, $message, $context )
            : \sprintf(
                '[%s] %s [%s] %s',
                $this->get_timestamp(),
                \strtoupper( $level ),
                $this->context,
                $message,
            );

        if ( $context ) {
            $message .= PHP_EOL . $this->indent( $this->ctx_to_string( $context ) );
        }

        return $message . PHP_EOL;
    }

    /**
     * Allows for custom formatting of the log message.
     *
     * @param  string              $level   The Log Level of the message.
     * @param  string|Stringable   $message The message to log.
     * @param  array<string,mixed> $context The context.
     * @return string
     */
    private function custom_format( string $level, string|Stringable $message, array $context ): string {
        $parts = array(
            'context'       => \wp_json_encode( $context ),
            'date'          => $this->get_timestamp(),
            'level'         => \strtoupper( $level ),
            'level-padding' => \str_repeat( ' ', 9 - \strlen( $level ) ),
            'message'       => $message,
            'priority'      => $this->levels[ $level ],
        );

        $message = $this->options['log_format'];

        foreach ( $parts as $part => $value ) {
            $message = \str_replace( '{' . $part . '}', $value, $message );
        }

        return $message;
    }

    /**
     * Gets the correctly formatted Date/Time for the log entry.
     *
     * PHP DateTime is dump, and you have to resort to trickery to get microseconds
     * to work correctly, so here it is.
     *
     * @return string
     */
    private function get_timestamp(): string {
        $original_time = \microtime( true );
        $date          = new DateTime( \gmdate( 'Y-m-d H:i:s', (int) $original_time ) );

        return $date->format( $this->options['date_format'] );
    }

    /**
     * Takes the given context and coverts it to a string.
     *
     * @param  array<string,mixed> $context The Context.
     * @return string
     */
    protected function ctx_to_string( array $context ): string {
        $export = '';
        foreach ( $context as $key => $value ) {
            $export .= "{$key}: ";
            $export .= \preg_replace(
                array(
                    '/=>\s+([a-zA-Z])/im',
                    '/array\(\s+\)/im',
                    '/^  |\G  /m',
                ),
                array(
                    '=> $1',
                    'array()',
                    '    ',
                ),
                \str_replace( 'array (', 'array(', \var_export( $value, true ) ),
            );
            $export .= PHP_EOL;
        }
        return \str_replace( array( '\\\\', '\\\'' ), array( '\\', '\'' ), \rtrim( $export ) );
    }

    /**
     * Indents the given string with the given indent.
     *
     * @param  string $text   The string to indent.
     * @param  string $indent What to use as the indent.
     * @return string
     */
    protected function indent( string $text, string $indent = '    ' ): string {
        return $indent . \str_replace( "\n", "\n" . $indent, $text );
    }
}
