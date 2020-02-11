<?php

namespace mykemeynell\Application;

/**
 * Class Container.
 *
 * @package mykemeynell\Application
 */
class Container
{
    /**
     * Bindings that have been registered within this container.
     *
     * @var array
     */
    protected array $bindings = [];

    /**
     * Tracked instances of objects that are registered within this container.
     *
     * @var array
     */
    protected array $instances = [];

    /**
     * The current instance state of this Container class.
     *
     * @var \mykemeynell\Application\Container|null
     */
    protected static $containerInstance;

    /**
     * Get the current instance of this Container class.
     *
     * @return \mykemeynell\Application\Container
     */
    public static function getContainerInstance(): Container
    {
        if (is_null(static::$containerInstance)) {
            static::$containerInstance = new static;
        }

        return static::$containerInstance;
    }

    /**
     * Add a binding to the bindings array.
     *
     * @param $abstract
     * @param $concrete
     *
     * @return void
     */
    public function bind($abstract, $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Get a binding from within the container.
     *
     * @param $abstract
     *
     * @return mixed
     * @throws \ReflectionException
     */
    protected function binding($abstract)
    {
        if (is_callable($abstract) || !$this->bindingExists($abstract)) {
            return $abstract;
        }

        $binding = $this->bindings[$abstract];

        if (is_a($binding, \Closure::class)) {
            return $this->make($binding);
        }

        return $binding;
    }

    /**
     * Test whether a binding already exists within the container.
     *
     * @param $abstract
     *
     * @return bool
     */
    protected function bindingExists($abstract): bool
    {
        return array_key_exists($abstract, $this->bindings);
    }

    /**
     * Store an instance against an abstract string.
     *
     * @param $abstract
     * @param $concrete
     *
     * @return void
     * @throws \ReflectionException
     */
    public function singleton($abstract, $concrete = null): void
    {
        $this->instances[$abstract] = $this->make($concrete);
    }

    /**
     * Get a singleton instance from within the container.
     *
     * @param $abstract
     *
     * @return mixed
     */
    protected function instance($abstract)
    {
        if (!$this->singletonExists($abstract)) {
            return $abstract;
        }

        return $this->instances[$abstract];
    }

    /**
     * Test whether an instance has been created and saved to this container.
     *
     * @param $abstract
     *
     * @return bool
     */
    protected function singletonExists($abstract): bool
    {
        return array_key_exists($abstract, $this->instances);
    }

    /**
     * Make or retrieve an instance of a given target.
     *
     * @param       $target
     * @param mixed ...$args
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public function make($target, ...$args)
    {
        if (!is_callable($target) && $this->singletonExists($target)) {
            return $this->instance($target);
        }

        // Check if there are any bindings that are set up against the given target.
        $target = $this->binding($target);


        // Determine if what is being reflected on is should be treated as
        // a function/helper or object.
        $reflector = is_callable($target)
            ? new \ReflectionFunction($target)
            : new \ReflectionClass($target);



        if (is_a($reflector, \ReflectionFunction::class)) {
            /** @var \ReflectionFunction $reflector */
            $parameters = $reflector->getParameters();
            $dependencies = $this->getDependencies($parameters, $args);

            return $reflector->invokeArgs($dependencies);
        }

        if (is_a($reflector, \ReflectionClass::class)) {
            /** @var \ReflectionClass $reflector */

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

            $parameters = $constructor->getParameters();
            $dependencies = $this->getDependencies($parameters, $args);

            return $reflector->newInstanceArgs($dependencies);
        }

        // Somehow '$reflector' has been initialised as something that isn't a
        // ReflectionFunction or ReflectionClass.
        throw new \ReflectionException("An unknown reflection type was created." . get_class($reflector));
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
    protected function getDependencies(array $parameters, array $args)
    {
        $dependencies = [];

        foreach ($parameters as $key => $parameter) {
            if (array_key_exists($key, $args)) {
                $dependencies[] = $args[$key];
                continue;
            }

            $dependency = $parameter->getClass();

            $dependencies[] = is_null($dependency)
                ? $this->resolveNonClass($parameter)
                : $this->make($dependency->name);
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
    protected function resolveNonClass(\ReflectionParameter $parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new \ReflectionException("Failed to resolve unknown parameter [{$parameter->getName()}] at position [{$parameter->getPosition()}].");
    }
}