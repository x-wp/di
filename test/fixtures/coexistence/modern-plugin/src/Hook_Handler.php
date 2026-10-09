<?php
/**
 * Modern hook declarations.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DI\E2E\Modern;

use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Dynamic_Filter;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;

#[Handler( tag: 'init', priority: 20, strategy: Handler::INIT_AUTO )]
final class Hook_Handler {
    public function __construct( private Message_Service $service ) {}

    public function on_initialize(): void {
        record( 'handler.initialize', $this->service->message );
    }

    #[Filter( tag: 'coexist_e2e_modern_filter', args: 2 )]
    public function filter_value( string $value, string $suffix ): string {
        $result = $value . ':' . $this->service->message . ':' . $suffix;
        record( 'filter.callback', $result );
        return $result;
    }

    #[Action( tag: 'coexist_e2e_modern_action', args: 2 )]
    public function action_value( string $value, string $suffix ): void {
        record( 'action.callback', $value . ':' . $this->service->message . ':' . $suffix );
    }

    #[Dynamic_Filter( tag: 'coexist_e2e_modern_dynamic_%s', vars: array( 'alpha' => 'mapped-alpha', 'beta' => 'mapped-beta' ), args: 1 )]
    public function dynamic_value( string $value, string $mapped ): string {
        return $value . ':' . $this->service->message . ':' . $mapped;
    }

    /** @param array<string> $plugins @return array<string> */
    #[Filter( tag: 'coexist_e2e_shared', priority: 20 )]
    public function shared_value( array $plugins ): array {
        $plugins[] = $this->service->message;
        return $plugins;
    }
}
