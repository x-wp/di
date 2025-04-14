<?php
/**
 * Admin_Module class file.
 *
 * @package Example
 */

namespace Example\Admin;

use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Module;

/**
 * Admin module.
 */
#[Module(
    container: 'example',
    hook: 'init',
    priority: 10,
    handlers: array(
        Handlers\Admin_Ajax_Handler::class,
    ),
)]
class Admin_Module {
    /**
     * Modules can have their own actions and filters.
     *
     * This one adds a settings link to the plugin action links.
     *
     * We use `modifiers` parameter to dynamically set the hook tag.
     * The `modifiers` parameter is a string or an array of strings that will be used to replace the `%s` in the hook tag.
     *
     * @param  array<string,string> $links Plugin Action links.
     * @return array<string,string>
     */
    #[Filter( tag: 'plugin_action_links_%s', priority: 10, context: Filter::CTX_ADMIN, modifiers: \XWPEX_BASE )]
    public function plugin_action_links( array $links ): array {
        $action_links = array(
            'settings' => \sprintf(
                '<a href="%s" aria-label="%s">%s</a>',
                \admin_url( 'options.php?page=example-settings' ),
                \esc_attr__( 'Settings' ),
                \esc_attr__( 'Settings' ),
            ),
        );

        return \array_merge( $action_links, $links );
    }
}
