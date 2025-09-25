<?php

namespace XWP\DIT\Core\Handlers;

use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;

#[Handler( tag: 'init', priority: 10, context: Handler::CTX_FRONTEND )]
class Body_Handler {
    /**
     * Change the body class by adding a unique identifier.
     *
     * @param  array<string> $classes The existing body classes.
     * @return array<string>
     */
    #[Filter( tag: 'body_class', priority: 10 )]
    public function change_body_class( array $classes ): array {
        $classes[] = 'di-test-' . \uniqid();

        return $classes;
    }
}
