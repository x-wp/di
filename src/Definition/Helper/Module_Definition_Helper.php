<?php

namespace XWP\DI\Definition\Helper;

use XWP\DI\Attributes\Module;
use XWP\DI\Core\Module_Wrapper;
use XWP\DI\Definition\Module_Definition;

class Module_Definition_Helper extends Hook_Definition_Helper {
    /**
     * The class of the hook definition that will be created by this helper.
     *
     * @var class-string<Hook_Definition>
     */
    protected const DEFINITION_CLASS = Module_Definition::class;

    /**
     * The class of the provider that will be used to create the hook definition.
     *
     * @var class-string<TPvd>
     */
    protected const PROVIDER_CLASS = Module_Wrapper::class;
}
