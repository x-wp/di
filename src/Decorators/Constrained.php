<?php

namespace XWP\DI\Decorators;

use Attribute;

#[Attribute( Attribute::TARGET_METHOD )]
class Constrained extends Invocation {
    public function __construct(
        protected int $limit = 1,
    ) {
    }

    public function get_args() {
        return array(
            'limit' => $this->limit,
        );
    }
}
