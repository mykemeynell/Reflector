<?php

namespace mykemeynell\Application;

/**
 * Class DI.
 *
 * @package mykemeynell\Application
 */
class DI {
    /**
     * Resolve a target.
     *
     * @param       $target
     * @param mixed ...$args
     *
     * @return object
     * @throws \ReflectionException
     */
    public static function resolve($target, ...$args)
    {
        $reflector = new \ReflectionClass($target);

        // Check that the target class can be instantiated.
        // i.e is not of type Trait or Interface.
        if (!$reflector->isInstantiable()) {
            throw new \ReflectionException("Target [{$reflector->getName()}] is not instantiable");
        }

        // Now that we have determined that the target can be instantiated, we
        // can move on to extracting the constructor.
        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            // The target has no __construct method - so we can return the
            // target as is.
            return $reflector->newInstanceWithoutConstructor();
        }

        // Here we get the parameters and determine the dependencies that
        // a constructor requires. We also pass through the parameters that have
        // been passed from the $this->make($target, ...$parameters).
        $parameters = $constructor->getParameters();
        $dependencies = static::getDependencies($parameters, $parameters);

        // Create a new instance with arguments.
        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Build up a list of dependencies for a given methods parameters.
     *
     * @param array $parameters
     * @param array $args
     *
     * @return array
     * @throws \ReflectionException
     */
    protected static function getDependencies(array $parameters, array $args)
    {
        $dependencies = [];

        foreach ($parameters as $key => $parameter) {
            if (array_key_exists($key, $args)) {
                $dependencies[] = $args[$key];
                continue;
            }

            $dependency = $parameter->getClass();

            $dependencies[] = is_null($dependency)
                ? static::resolveNonClass($parameter)
                : static::resolve($dependency->name);
        }

        return $dependencies;
    }

    /**
     * Determine what to do with a non-class value.
     *
     * @param \ReflectionParameter $parameter
     *
     * @return mixed
     * @throws \ReflectionException
     */
    protected static function resolveNonClass(\ReflectionParameter $parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $message = sprintf("Failed to resolve unknown parameter [%s] at position [%s]",
            $parameter->getName(),
            $parameter->getPosition()
        );

        throw new \ReflectionException($message);
    }
}
