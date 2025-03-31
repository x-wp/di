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
     * KLogger options
     *  Anything options not considered 'core' to the logging library should be
     *  settable view the third parameter in the constructor
     *
     *  Core options include the log file path and the log threshold
     *
     * @var array{
     *   context: string,
     *   date_format: string,
     *   extension: string,
     *   filename: string|null,
     *   max_lines: false|int,
     *   log_format: false|string,
     *   prefix: string,
     * }
     */
    protected $options = array(
        'context'     => 'App',
        'date_format' => 'G:i:s',
        'extension'   => 'txt',
        'filename'    => null,
        'log_format'  => false,
        'max_lines'   => false,
        'prefix'      => 'log_',
    );

    /**
     * Path to the log file
     *
     * @var string
     */
    private $file_path;

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
     * This holds the file handle for this instance's log file
     *
     * @var ?resource
     */
    private mixed $handle;

    /**
     * This holds the last line logged to the logger
     *  Used for unit tests
     *
     * @var string
     */
    private $last_line = '';

    /**
     * Class constructor
     *
     * @param string              $basedir      File path to the logging directory.
     * @param string              $level Log level threshold.
     * @param array<string,mixed> $options Extra options to set.
     *
     * @throws \RuntimeException If the directory can't be created.
     */
    public function __construct( protected string $basedir, protected string $level = LogLevel::DEBUG, array $options = array() ) {
        $this->level   = $level;
        $this->options = \xwp_parse_args( $options, $this->options );

        if ( \str_starts_with( $basedir, 'php://' ) ) {
            $this->log_to_stdout( $basedir );
            $this->set_handle( 'w+' );
            return;
        }

        $this->set_file_path( $basedir );
        $this->set_handle( 'a' );

        if ( ! isset( $this->handle ) ) {
            throw new \RuntimeException( 'The file could not be opened. Check permissions.' );
        }
    }

    /**
     * Returns a new instance with the given context
     *
     * @param  string $context The context.
     * @return self
     */
    public function with_context( string $context ): self {
        return new self(
            $this->basedir,
            $this->level,
            \array_merge( $this->options, array( 'context' => $context ) ),
        );
    }

    /**
     * Log to stdout
     *
     * @param string $stdout_path Path to stdout.
     */
    public function log_to_stdout( string $stdout_path ): void {
        $this->file_path = $stdout_path;
    }

    /**
     * Sets the file path for this instance
     *
     * @param string $basedir File path to the logging directory.
     *
     * @throws \RuntimeException If the directory can't be created.
     */
    public function set_file_path( string $basedir ): void {
        if ( ! \wp_mkdir_p( $basedir ) ) {
            throw new \RuntimeException(
                'The directory could not be created. Check that appropriate permissions have been set.',
            );
        }

        $filename = $this->options['filename'] ?? \sprintf(
            '%s-%s.%s',
            \rtrim( $this->options['prefix'], '_-' ),
            \gmdate( 'Y-m-d' ),
            $this->options['extension'],
        );

        $this->file_path = $basedir . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Sets the file handle for this instance
     *
     * @param string $mode File mode.
     */
    public function set_handle( string $mode ): void {
        $this->handle = \fopen( $this->file_path, $mode );
    }

    /**
     * Class destructor
     */
    public function __destruct() {
        if ( ! isset( $this->handle ) ) {
            return;
        }

        \fclose( $this->handle );
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
        if ( null === $this->handle ) {
            return;
        }

        if ( false === \fwrite( $this->handle, $message ) ) {
            throw new \RuntimeException(
                'The file could not be written to. Check that appropriate permissions have been set.',
            );
        }

        $this->last_line = \trim( $message );
        ++$this->line_count;

        if ( ! $this->options['max_lines'] || 0 !== $this->line_count % $this->options['max_lines'] ) {
            return;
        }

        \fflush( $this->handle );
    }

    /**
     * Get the file path that the log is currently writing to
     *
     * @return string
     */
    public function get_file_path(): string {
        return $this->file_path;
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
                $this->options['context'],
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
