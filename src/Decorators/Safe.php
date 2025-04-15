<?php

namespace XWP\DI\Decorators;

use Attribute;

#[Attribute( Attribute::TARGET_METHOD )]
class Safe extends Invocation {
    public function __construct(
        protected ?array $catch = null,
        protected array|string|null $handler = null,
    ) {
    }

    public function get_args(): array {
        return array(
            'catch'   => $this->catch ?? true,
            'handler' => $this->handler ?? false,
        );
    }
}
