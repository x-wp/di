<?php

namespace XWP\DI;

use XWP\DI\Core\App_Config;
use XWP\DI\Core\App_Context;

class App extends App_Context {
    public function __construct(
        Container $container,
        protected readonly App_Config $config,
        protected readonly string $entry_module,
    ) {
        parent::__construct( $container );
    }

    /**
     * Run the application.
     *
     * @return static
     */
    public function run( string $tag = 'plugins_loaded', int $priority = 10 ): static {
        $this->container->call(
            'xwp.runner::register',
            array(
                'hook'     => \DI\get( 'xwp.m:entry' ),
                'priority' => $priority,
                'tag'      => $tag,
            ),
        );

        return $this;
    }
}
