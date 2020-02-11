<?php

if(! function_exists('container')) {
    /**
     * Resolve a class from the current container.
     *
     * @param string $target
     * @param array  $args
     *
     * @return \mykemeynell\Application\Container
     */
    function container(?string $target = null, ...$args)
    {
        $container = \mykemeynell\Application\Container::getContainerInstance();

        if(empty($target)) {
            return $container;
        }

        return call_user_func_array([$container, 'make'], func_get_args());
    }
}
