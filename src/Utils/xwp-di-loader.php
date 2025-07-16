<?php //phpcs:disable SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall

if ( ! function_exists( 'xwp_di_bootstrap' ) && function_exists( 'add_action' ) && ! did_action( 'plugins_loaded' ) ) :
    /**
     * Fires the `xwp_di_bootstrap` action.
     *
     * This function is intended to be called during the `plugins_loaded` action
     */
    function xwp_di_bootstrap(): void {
        do_action( 'xwp_di_bootstrap' );
    }

    add_action( 'plugins_loaded', 'xwp_di_bootstrap', -10000, 0 );
endif;
