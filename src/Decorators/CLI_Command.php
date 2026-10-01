<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, Universal.Operators.DisallowShortTernary.Found

namespace XWP\DI\Decorators;

use Closure;
use XWP\DI\Interfaces\Can_Handle_CLI;

/**
 * Decorator for defining CLI commands.
 *
 * @template T of object
 * @extends Action<T,Can_Handle_CLI<T>>
 */
#[\Attribute( \Attribute::TARGET_METHOD )]
class CLI_Command extends Action {
    /**
     * Declared subcommand.
     *
     * @var string
     */
    protected string $subcommand;

    /**
     * Declared cmd args.
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
     * Export unbound attribute metadata.
     *
     * @internal Discovery metadata detail.
     * @return array<string,mixed>
     */
    public function get_declaration(): array {
        return array(
            'after'       => $this->after,
            'args'        => $this->cmd_args,
            'before'      => $this->before,
            'command'     => $this->subcommand,
            'deferred'    => $this->deferred,
            'description' => $this->description,
            'params'      => $this->params,
            'summary'     => $this->summary,
            'when'        => $this->when,
        );
    }
}
