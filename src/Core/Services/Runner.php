<?php

namespace XWP\DI\Core\Services;

class Runner {
    public function register( object $hook, ?string $tag, ?int $priority ): void {
        \dump( $hook, $tag, $priority );
        die;
    }
}
