<?php

namespace XWP\DI\Core;

use XWP\DI\Container;

class Module_Wrapper {
    public function __construct(
        protected readonly string $metatype,
        protected readonly Container $container,
    ) {
    }
}
