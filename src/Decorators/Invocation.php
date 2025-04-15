<?php

namespace XWP\DI\Decorators;

abstract class Invocation {
    /**
     * Get the arguments for the invocation.
     *
     * @return array<string,mixed>
     */
    abstract public function get_args(): array;
}
