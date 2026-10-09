<?php
/** Observed beta incompatibilities; each entry must link a reconciliation issue. */
return array(
    'startup.import_once' => array( 'actual' => array(), 'issue' => 'di-0sy' ),
    'container.typed_return' => array( 'error' => 'TypeError', 'contains' => 'Return value must be of type DI\\Container, XWP\\DI\\App returned', 'issue' => 'di-qla' ),
    'container.declared_has_method' => array( 'actual' => false, 'issue' => 'di-qla' ),
    'helpers.module_lookup' => array( 'error' => 'Error', 'contains' => 'undefined function xwp_get_module()', 'issue' => 'di-1kf' ),
    'helpers.handler_registration' => array( 'error' => 'TypeError', 'contains' => 'must be of type XWP\\DI\\Interfaces\\Can_Handle, string given', 'issue' => 'di-1kf' ),
    'helpers.extend_named_arguments' => array( 'error' => 'Error', 'contains' => 'Unknown named parameter $container', 'issue' => 'di-1kf' ),
    'helpers.option_definition' => array( 'error' => 'Error', 'contains' => 'undefined function XWP\\DI\\option()', 'issue' => 'di-lz1' ),
    'helpers.transient_definition' => array( 'error' => 'Error', 'contains' => 'undefined function XWP\\DI\\transient()', 'issue' => 'di-lz1' ),
    'helpers.filtered_definition' => array( 'error' => 'Error', 'contains' => 'undefined function XWP\\DI\\filtered()', 'issue' => 'di-lz1' ),
    'decorators.positional_module' => array( 'error' => 'TypeError', 'contains' => 'Argument #2 ($priority) must be of type int, string given', 'issue' => 'di-nfa' ),
);
