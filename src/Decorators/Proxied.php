<?php

namespace XWP\DI\Decorators;

use Attribute;

#[Attribute( Attribute::TARGET_METHOD )]
class Proxied extends Invocation {
    public function __construct(
        protected int $args = 0,
        protected array $params = array(),
    ) {}

    public function get_args(): array {
        return array(
            'args'   => $this->args,
            'params' => $this->params,
            'proxy'  => true,
        );
    }
}
