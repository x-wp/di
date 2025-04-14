<?php
/**
 * Product_Page_Handler class file.
 *
 * @package Example
 */

namespace Example\WC\Handlers;

use WC_Product;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

/**
 * Product page handler.
 *
 * This handler uses just-in-time initialization strategy.
 * Handler will be initialized just before the first registered hook is fired.
 */
#[Handler(
    tag: 'init',
    priority: 10,
    context: Handler::CTX_FRONTEND,
    strategy: Handler::INIT_JUST_IN_TIME,
    container: 'example',
)]
class Product_Page_Handler {
    /**
     * Checks if we can initialize this handler.
     *
     * We can initialize this handler if we are on the product page.
     *
     * @return bool
     */
    public static function can_initialize(): bool {
        return \is_product();
    }
    /**
     * Displays a notice on the single product page and before the Add to Cart button.
     *
     * One function can be used for multiple hooks.
     * This also demonstrates special injection tokens.
     * These are:
     *  - !self.hook        - Hook decorator
     *  - !self.handler     - Handler decorator
     *  - !value:$VALUE     - Any value
     *  - !global:$VARIABLE - Any global variable
     *  - !const:$CONSTANT  - Any constant
     *
     * We're injecting the hook decorator itself in order to demonstrate the use of the `!self.hook` token.
     *
     * @param  WC_Product $product Product object.
     * @param  Action     $hook    Action object.
     */
    #[Action(
        tag: 'woocommerce_single_product_summary',
        priority: 10,
        invoke: Action::INV_PROXIED,
        args: 0,
        params: array( '!global:product', '!self.hook' ),
    )]
    #[Action(
        tag: 'woocommerce_before_add_to_cart',
        priority: 10,
        invoke: Action::INV_PROXIED,
        args: 0,
        params: array( '!global:product', '!self.hook' ),
    )]
    public function show_notice( WC_Product $product, Action $hook ): void {
        $text = 'woocommerce_before_add_to_cart' === $hook->tag
            ? 'Before Add to Cart'
            : 'Single Product Summary';

        \printf(
            '<p class="woocommerce-info">%s: %s</p>',
            \esc_html( $text ),
            \esc_html( $product->get_name() ),
        );
    }
}
