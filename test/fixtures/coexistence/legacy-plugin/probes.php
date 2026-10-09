<?php
/**
 * Independent behavioral probes for an unchanged v1 plugin client.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DI\E2E\Legacy;

use XWP\DI\Decorators\Module;
use XWP\DI\Interfaces\Can_Handle;

/** Record observable lifecycle and callback effects. */
function record( string $event, mixed $value ): void {
    $GLOBALS['xwp_di_e2e_events'][] = array(
        'plugin' => 'legacy',
        'event'  => $event,
        'value'  => $value,
        'hook'   => \current_action(),
    );
}

/** @return array<mixed> */
function event_values( string $event ): array {
    $values = array();
    foreach ( $GLOBALS['xwp_di_e2e_events'] ?? array() as $entry ) {
        if ( 'legacy' === $entry['plugin'] && $event === $entry['event'] ) {
            $values[] = $entry['value'];
        }
    }
    return $values;
}

/** A real v1 consumer can promise a PHP-DI container to its caller. */
function typed_container(): \DI\Container {
    return \xwp_app( 'coexist_legacy' );
}

/**
 * Run once after WordPress has booted. Errors must not hide later probes.
 *
 * @return array<string,array{expected:mixed,actual?:mixed,error?:array{class:string,message:string}}>
 */
function probes(): array {
    $checks = array(
        'startup.root_once' => array( array( 'legacy' ), static fn() => event_values( 'root.initialize' ) ),
        'startup.import_once' => array( array( 'legacy-import' ), static fn() => event_values( 'import.initialize' ) ),
        'startup.handler_once' => array( array( 'legacy' ), static fn() => event_values( 'handler.initialize' ) ),
        'definitions.root' => array( 'legacy-root', static fn() => \xwp_app( 'coexist_legacy' )->get( 'coexist.root' ) ),
        'definitions.import' => array( 'legacy-import', static fn() => \xwp_app( 'coexist_legacy' )->get( 'coexist.import' ) ),
        'service.constructor_injection' => array( array( 'legacy' ), static fn() => event_values( 'service.construct' ) ),
        'service.singleton' => array( true, static fn() => \xwp_app( 'coexist_legacy' )->get( Message_Service::class ) === \xwp_app( 'coexist_legacy' )->get( Message_Service::class ) ),
        'hooks.filter_arguments' => array( 'value:legacy:suffix', static fn() => \apply_filters( 'coexist_e2e_legacy_filter', 'value', 'suffix' ) ),
        'hooks.filter_once' => array( array( 'value:legacy:suffix' ), static fn() => event_values( 'filter.callback' ) ),
        'hooks.action_arguments' => array( array( 'first:legacy:one', 'second:legacy:two' ), static function (): array {
            \do_action( 'coexist_e2e_legacy_action', 'first', 'one' );
            \do_action( 'coexist_e2e_legacy_action', 'second', 'two' );
            return event_values( 'action.callback' );
        } ),
        'hooks.dynamic_filter' => array( array( 'value:legacy:mapped-alpha', 'value:legacy:mapped-beta' ), static fn() => array(
            \apply_filters( 'coexist_e2e_legacy_dynamic_alpha', 'value' ),
            \apply_filters( 'coexist_e2e_legacy_dynamic_beta', 'value' ),
        ) ),
        'container.lookup' => array( true, static fn() => \xwp_has( 'coexist_legacy' ) && \xwp_app( 'coexist_legacy' )->has( 'coexist.message' ) ),
        'container.typed_return' => array( 'legacy', static fn() => typed_container()->get( 'coexist.message' ) ),
        'container.declared_has_method' => array( true, static fn() => \method_exists( \xwp_app( 'coexist_legacy' ), 'has' ) ),
        'helpers.module_lookup' => array( true, static fn() => \xwp_get_module( Root_Module::class ) instanceof Module ),
        'helpers.handler_registration' => array( true, static fn() => \xwp_register_hook_handler( Hook_Handler::class ) instanceof Can_Handle ),
        'helpers.extend_named_arguments' => array( array( Child_Module::class ), static function (): array {
            \xwp_extend_app( container: 'coexist_legacy_extension_probe', module: Child_Module::class, position: 'after', target: Root_Module::class );
            return \apply_filters( 'xwp_extend_import_coexist_legacy_extension_probe', array(), Root_Module::class );
        } ),
        'helpers.option_definition' => array( 'option-default', static function (): mixed {
            \xwp_app( 'coexist_legacy' )->set( 'coexist.probe.option', \XWP\DI\option( 'coexist_e2e_missing_option', 'option-default' ) );
            return \xwp_app( 'coexist_legacy' )->get( 'coexist.probe.option' );
        } ),
        'helpers.transient_definition' => array( 'transient-default', static function (): mixed {
            \xwp_app( 'coexist_legacy' )->set( 'coexist.probe.transient', \XWP\DI\transient( 'coexist_e2e_missing_transient', 'transient-default' ) );
            return \xwp_app( 'coexist_legacy' )->get( 'coexist.probe.transient' );
        } ),
        'helpers.filtered_definition' => array( 'value:legacy:suffix', static function (): mixed {
            \xwp_app( 'coexist_legacy' )->set( 'coexist.probe.filtered', \XWP\DI\filtered( 'coexist_e2e_legacy_filter', 'value', 'suffix' ) );
            return \xwp_app( 'coexist_legacy' )->get( 'coexist.probe.filtered' );
        } ),
        'decorators.positional_module' => array( true, static fn() => ( new Module( 'coexist_legacy', 'init', 10 ) ) instanceof Module ),
    );

    $results = array();
    foreach ( $checks as $name => [ $expected, $check ] ) {
        try {
            $results[ $name ] = array( 'expected' => $expected, 'actual' => $check() );
        } catch ( \Throwable $error ) {
            $results[ $name ] = array(
                'expected' => $expected,
                'error'    => array( 'class' => $error::class, 'message' => $error->getMessage() ),
            );
        }
    }
    return $results;
}
