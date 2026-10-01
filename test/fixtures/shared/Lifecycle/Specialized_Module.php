<?php
/**
 * Cacheable specialized callbacks.
 *
 * @package XWP\DI\Tests
 */
namespace XWP\DIT\Lifecycle;

use XWP\DI\Decorators\Ajax_Action;
use XWP\DI\Decorators\Ajax_Handler;
use XWP\DI\Decorators\CLI_Command;
use XWP\DI\Decorators\CLI_Handler;
use XWP\DI\Decorators\Module;
use XWP\DI\Hook\Ajax_Callback;
use XWP\DI\Hook\REST_Callback;
use XWP\DI\Hook\CLI_Callback;
use XWP\DI\Decorators\REST_Handler;
use XWP\DI\Decorators\REST_Route;

#[Module( hook: 'specialized_module', handlers: array( Specialized_Ajax::class, Specialized_REST::class, Specialized_CLI::class ) )]
final class Specialized_Module {}

#[Ajax_Handler( 'specialized' )]
final class Specialized_Ajax {
    public ?Ajax_Callback $view = null;

    #[Ajax_Action( 'fetch', params: array( '!self.hook' ) )]
    public function fetch( Ajax_Callback $view ): void {
        $this->view = $view;
    }
}

#[REST_Handler( 'specialized/v1', 'items' )]
final class Specialized_REST extends \XWP_REST_Controller {
    public ?REST_Callback $view = null;

    #[REST_Route( 'fetch', 'GET', params: array( '!self.hook' ) )]
    public function fetch( \WP_REST_Request $request, REST_Callback $view ): array {
        $this->view = $view;
        return array( 'cached' => true );
    }
}

#[CLI_Handler( 'specialized' )]
final class Specialized_CLI {
    public ?CLI_Callback $view = null;

    #[CLI_Command( 'fetch', params: array( 'view' => '!self.hook' ) )]
    public function fetch( array $flags, CLI_Callback $view ): void {
        $this->view = $view;
    }
}
