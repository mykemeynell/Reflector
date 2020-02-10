<?php

if(! function_exists('reflect')) {
    /**
     * Resolve a class.
     *
     * @param string $target
     * @param array  $args
     *
     * @return \mykemeynell\Reflect\Reflect|mixed
     */
    function reflect(?string $target = null, ...$args)
    {
        $reflector = \mykemeynell\Reflect\Reflect::getReflectorInstance();

        if(empty($target)) {
            return $reflector;
        }

        return call_user_func_array([$reflector, 'resolve'], func_get_args());
    }
}
