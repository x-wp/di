<?php
/**
 * Post_List_Page_Handler class file.
 *
 * @package Example
 */

namespace Example\Admin\Handlers;

use Example\Interfaces\Config_Interface;
use WP_Query;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;
use XWP\DI\Interfaces\Can_Initialize;

/**
 * Example handler with defined context and conditional initialization.
 *
 * This handler and its callbacks will only be registered and executed if the following is true:
 *  - The `val2` option is set to `true`.
 *  - The current screen is the post list page.
 *  - The current post type is `post`.
 */
#[Handler( tag: 'current_screen', priority: 10, context: Handler::CTX_ADMIN, container: 'example' )]
class Post_List_Page_Handler implements Can_Initialize {
    /**
     * Checks if we can initialize this handler.
     *
     * @param  ?Config_Interface $cfg Configuration object. Injected by the container.
     * @return bool
     */
    public static function can_initialize( ?Config_Interface $cfg = null ): bool {
        $screen = \get_current_screen();

        return $cfg->get( 'val2' ) && 'post' === $screen->base && 'post' === $screen->post_type;
    }

    /**
     * Filter the post list query.
     *
     * @param  WP_Query $query WP_Query instance.
     */
    #[Action( tag: 'pre_get_posts', priority: 10 )]
    public function change_post_list( WP_Query $query ): void {
        $query->set( 'meta_key', 'related' );
        $query->set( 'meta_value', 'val' );
    }
}
