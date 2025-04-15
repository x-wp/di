<?php

namespace XWP\DI\Decorators;

class Recursive extends Invocation {
    public function __construct( protected bool $recursive = true ) {
    }

    public function get_args(): array {
        return array(
            'recursive' => $this->recursive,
        );
    }
}
