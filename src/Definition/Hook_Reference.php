<?php
/**
 * Hook_Reference class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Definition;

use DI\Definition\Reference;
use XWP\DI\Traits\Hook_Token_Methods;

/**
 * Represents a reference to another hook.
 */
class Hook_Reference extends Reference {
    use Hook_Token_Methods;

    /**
     * Constructor.
     *
     * @param  string $target Target hook name.
     */
    public function __construct( string $target, ) {
        parent::__construct( $this->get_token( $target ) );
    }
}
