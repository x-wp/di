<?php

namespace XWP\DI\Core;

use XWP\DI\Container;

class App_Context {
    /**
     * Enabled application hooks.
     *
     * @var array<int, 'activation'|'deactivation'>
     */
    protected array $hooks = array();

    /**
     * Context constructor.
     *
     * @param  Container $container Container instance.
     */
    public function __construct(
        protected readonly Container $container,
    ) {
    }

    /**
     * Enable application hooks.
     *
     * @param  'activation'|'deactivation' ...$hooks Hooks to enable.
     * @return static
     */
    public function enable_hooks( string ...$hooks ): static {
        $this->hooks = $hooks;

        return $this;
    }
}
