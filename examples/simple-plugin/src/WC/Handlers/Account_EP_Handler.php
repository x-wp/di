<?php
/**
 * Account_EP_Handler class file.
 *
 * @package Example
 */

namespace Example\WC\Handlers;

use Example\WC\Services\Endpoint_Data_Service;
use WC_Data_Store;
use XWP\DI\Decorators\Dynamic_Action;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;

/**
 * Account endpoint handler.
 *
 * This handler is initialized in frontend and ajax contexts.
 * Even though it does not implement the `Can_Initialize` interface, check will still be performed.
 */
#[Handler(
	tag: 'init',
	priority: 10,
	context: Handler::CTX_FRONTEND | Handler::CTX_AJAX,
	container: 'example',
)]
class Account_EP_Handler {
    /**
     * Checks if we can initialize this handler.
     *
     * We can initialize this handler if we are on the account page.
     *
     * @return bool
     */
    public static function can_initialize(): bool {
        return \is_account_page();
    }

    /**
     * Custom endpoint callback.
     *
     * We use a `Dynamic_Action` decorator to dynamically hook into the endpoint tag and pass the argument.
     *
     * @param  string $ep Endpoint.
     *
     * @see WC_Module::configure() for the definition of the `cfg.wc.eps` array.
     */
    #[Dynamic_Action(
        tag: 'woocommerce_account_%s_endpoint', // Dynamic tag. %s will be replaced with the endpoint.
        context: Dynamic_Action::CTX_FRONTEND,  // Execution context.
        vars: 'cfg.wc.eps',                     // Array of strings, callback function, or container key. Keys will be used for the hook tag, values will be used for parameters.
        args: 0,                                // Number of arguments the hooked function will accept. Here it is 0 because hook requires no arguments.
    )]
    public function endpoint_callback( string $ep ): void {
        /**
         * Dynamically filter the arguments for the endpoint.
         *
         * @param  array $args Endpoint arguments.
         * @return array
         *
         * @since 1.0.0
         */
        $args = \apply_filters( "example_endpoint_args_{$ep}", array() );

        \wc_get_template( "myaccount/{$ep}.php", $args );
    }

    /**
     * Filter the arguments for the endpoint.
     *
     * This filter uses proxied invocation - function will not be called directly, but through the container.
     * This is useful when you want to inject specific dependencies into the function.
     * It's a bit slower than direct invocation, but it offers more flexibility.
     *
     * @param  array<string,mixed>   $args Endpoint arguments.
     * @param  Endpoint_Data_Service $epd Endpoint data service.
     * @return array<string,mixed>
     */
    #[Filter(
        tag: 'example_endpoint_args_ep-1',              // Filter tag.
        priority: 10,                                   // Filter priority.
        invoke: Filter::INV_PROXIED,                    // Invocation type. You can defined multiple types of invocation by using bitwise OR (explained in the next example).
        args: 1,                                        // Number of hook arguments. If injecting custom parameters, this must be set to the number of parameters.
        params: array( Endpoint_Data_Service::class ),  // Parameters to inject.
    )]
    public function ep1_data( array $args, Endpoint_Data_Service $epd ): array {
        $args['products'] = $epd->get_data( 'ep-1' );

        return $args;
    }

    /**
     * Filter the search results for products.
     *
     * This demonstrates advanced usage of the invocation strategy.
     * Types used are:
     *  - Filter::INV_PROXIED: Function will be called through the container.
     *  - Filter::INV_LOOPED:  Since we are calling the function which has the filter we are hooking into, this will prevent infinite loops.
     *  - Filter::INV_SAFELY:  This will catch any exceptions thrown by the function and return the unmodified result.
     *
     * @param  bool|array<int> $res   Search results.
     * @param  string          $search Search string.
     * @param  string          $filter Filter string. Injected by the container.
     * @return array<int>
     *
     * @throws \Exception If search fails.
     */
    #[Filter(
        tag: 'woocommerce_product_pre_search_products',
        priority: 100,
        invoke: Filter::INV_PROXIED | Filter::INV_LOOPED | Filter::INV_SAFELY,
        context: Filter::CTX_FRONTEND | Filter::CTX_AJAX,
        args: 2,
        params: array( 'cfg.wc.filter' ),
    )]
    public function modify_search( bool|array $res, string $search, string $filter ): bool|array {
        if ( false !== $res ) {
            return $res;
        }

        return 1 === \wp_rand( 1, 9999 ) % 2
            ? WC_Data_Store::load( 'product' )->search_products( $search . $filter )
            : throw new \Exception( 'Search failed' );
    }
}
