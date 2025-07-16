<?php

namespace XWP\DI\Core\Injector;

use XWP\DI\Utils\Reflection;

class Metadata_Scanner extends Reflection {
    private readonly array $cached;

    public function __construct() {
        $this->cached = array();
    }
}
