<?php
/**
 * Independent behavioral probes for the current beta plugin client.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DI\E2E\Modern;

use XWP\DI\App;
use XWP\DI\Container;

/** Record observable lifecycle and callback effects. */
function record( string $event, mixed $value ): void {
    $GLOBALS['xwp_di_e2e_events'][] = array(
        'plugin' => 'modern',
        'event'  => $event,
        'value'  => $value,
        'hook'   => \current_action(),
    );
}

/** @return array<mixed> */
function event_values( string $event ): array {
    $values = array();
    foreach ( $GLOBALS['xwp_di_e2e_events'] ?? array() as $entry ) {
        if ( 'modern' === $entry['plugin'] && $event === $entry['event'] ) {
            $values[] = $entry['value'];
        }
    }
    return $values;
}

/** Current clients receive the application separately from its container. */
function typed_app(): App {
    return \xwp_app( 'coexist_modern' );
}

/**
 * Run once after WordPress has booted. Errors must not hide later probes.
 *
 * @return array<string,array{expected:mixed,actual?:mixed,error?:array{class:string,message:string}}>
 */
function probes(): array {
    $checks = array(
        'startup.root_once' => array( array( 'modern' ), static fn() => event_values( 'root.initialize' ) ),
        'startup.import_once' => array( array( 'modern-import' ), static fn() => event_values( 'import.initialize' ) ),
        'startup.handler_once' => array( array( 'modern' ), static fn() => event_values( 'handler.initialize' ) ),
        'definitions.root' => array( 'modern-root', static fn() => \xwp_app( 'coexist_modern' )->get( 'coexist.root' ) ),
        'definitions.import' => array( 'modern-import', static fn() => \xwp_app( 'coexist_modern' )->get( 'coexist.import' ) ),
        'service.constructor_injection' => array( array( 'modern' ), static fn() => event_values( 'service.construct' ) ),
        'service.singleton' => array( true, static fn() => \xwp_app( 'coexist_modern' )->get( Message_Service::class ) === \xwp_app( 'coexist_modern' )->get( Message_Service::class ) ),
        'hooks.filter_arguments' => array( 'value:modern:suffix', static fn() => \apply_filters( 'coexist_e2e_modern_filter', 'value', 'suffix' ) ),
        'hooks.filter_once' => array( array( 'value:modern:suffix' ), static fn() => event_values( 'filter.callback' ) ),
        'hooks.action_arguments' => array( array( 'first:modern:one', 'second:modern:two' ), static function (): array {
            \do_action( 'coexist_e2e_modern_action', 'first', 'one' );
            \do_action( 'coexist_e2e_modern_action', 'second', 'two' );
            return event_values( 'action.callback' );
        } ),
        'hooks.dynamic_filter' => array( array( 'value:modern:mapped-alpha', 'value:modern:mapped-beta' ), static fn() => array(
            \apply_filters( 'coexist_e2e_modern_dynamic_alpha', 'value' ),
            \apply_filters( 'coexist_e2e_modern_dynamic_beta', 'value' ),
        ) ),
        'container.lookup' => array( true, static fn() => \xwp_has( 'coexist_modern' ) && \xwp_app( 'coexist_modern' )->has( 'coexist.message' ) ),
        'container.typed_return' => array( 'modern', static fn() => typed_app()->get( 'coexist.message' ) ),
        'container.started' => array( true, static fn() => typed_app()->started() ),
        'container.explicit_access' => array( true, static fn() => typed_app()->container() instanceof Container ),
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
