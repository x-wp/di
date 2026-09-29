<?php
/** @package XWP\DI\Tests */
namespace XWP\DIT\Lifecycle;

use XWP\DI\Decorators as D;
use XWP\DI\Interfaces\Can_Handle;

#[D\Module( 'xwp_discovery_review', handlers: array( Discovery_Review_Infused::class, Discovery_Review_Repeated::class ) )]
final class Discovery_Review_Module {}

#[\Attribute( \Attribute::TARGET_METHOD )]
final class Discovery_Review_Infuse extends D\Infuse {
    public function resolve( Can_Handle $handler ) {
        return array( 'custom-literal' );
    }
}

#[D\Handler( strategy: D\Handler::INIT_NOW )]
final class Discovery_Review_Infused {
    public string $initialized = '';

    #[Discovery_Review_Infuse( 'missing-service' )]
    public function on_initialize( string $value ): void {
        $this->initialized = $value;
    }

    #[D\Filter( 'xwp_discovery_infused', invoke: D\Filter::INV_PROXIED, args: 0, params: array( '!self.handler' ) )]
    #[Discovery_Review_Infuse( 'missing-service' )]
    public function value( Can_Handle $handler ): string {
        return $handler->get_params( __FUNCTION__ )->resolve( $handler )[0];
    }
}

#[D\Handler( strategy: D\Handler::INIT_NOW )]
final class Discovery_Review_Repeated {
    public array $tokens = array();

    #[Discovery_Review_Filter( 'xwp_discovery_legacy' )]
    #[Discovery_Review_Filter( 'xwp_discovery_legacy' )]
    public function legacy( string $value ): string {
        return $value . ':legacy';
    }


    #[D\Filter( 'xwp_discovery_same' )]
    #[D\Filter( 'xwp_discovery_same' )]
    public function same( string $value ): string {
        return $value . ':same';
    }

    #[D\Filter( 'xwp_discovery_views', params: array( '!self.hook' ), args: 1, invoke: D\Filter::INV_PROXIED )]
    #[D\Filter( 'xwp_discovery_views', params: array( '!self.hook' ), args: 1, invoke: D\Filter::INV_PROXIED )]
    public function views( string $value, D\Filter $hook ): string {
        $this->tokens[] = $hook->get_token();
        return $value;
    }

    #[D\Filter( 'xwp_discovery_repeat', priority: 10, invoke: D\Filter::INV_PROXIED )]
    #[D\Filter( 'xwp_discovery_repeat', priority: 20, invoke: D\Filter::INV_PROXIED )]
    public function value( string $value ): string {
        return $value . ':repeat';
    }

    #[D\Dynamic_Filter( 'xwp_discovery_%s', array( 'first' ) )]
    #[D\Dynamic_Filter( 'xwp_discovery_%s', array( 'second' ) )]
    public function dynamic( string $value, string $suffix ): string {
        return $value . ':' . $suffix;
    }
}

#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
final class Discovery_Review_Filter extends D\Filter {
    public function __construct( string $tag ) {
        parent::__construct( $tag );
    }

    public function invoke( mixed ...$args ): mixed {
        return parent::invoke( ...$args ) . ':override';
    }
}
