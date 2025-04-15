<?php


namespace XWP\DI\Core;

#[\AllowDynamicProperties]
class Test {
    protected array $config = array();

    /**
     * Test constructor.
     *
     * @param  string                        $tag
     * @param  callable|int|string|null|null $priority
     * @param  int                           $context
     * @param  array{
     *  classname?: string,
     *  handler?: string,
     *  method?: string,
     *  container?: Container,
     * } $args Arguments.
     */
    public function __construct(
        protected string $tag = '',
        protected callable|int|string|null $priority = null,
        protected int $context = Handler::CTX_GLOBAL,
        array $args = array(),
    ) {
        $this->config = $args;
    }
}
