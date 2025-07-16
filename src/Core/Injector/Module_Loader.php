<?php

namespace XWP\DI\Core\Injector;

use DI\Factory\RequestedEntry;

class Module_Loader {
    public function load( RequestedEntry $entry ): mixed {
        \dump( $entry );
        die;
        return '';
    }
}
