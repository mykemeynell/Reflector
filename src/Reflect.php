<?php

namespace mykemeynell\Reflect;

/**
 * Class Reflect.
 *
 * @package mykemeynell\Reflect
 */
class Reflect
{
    /**
     * All aliases that have been set up using this Reflect class.
     *
     * @var array
     */
    protected $aliases = [];

    /**
     * Holds all instances that have been created since this object was
     * instantiated.
     *
     * @var array
     */
    protected $instances = [];

    /**
     * Stores bindings.
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * The current instance state of this Reflect class.
     *
     * @var null|\mykemeynell\Reflect\Reflect
     */
    protected static $instance;

    /**
     * Get the current instance of this Reflect class.
     *
     * @return \mykemeynell\Reflect\Reflect
     */
    public static function getReflectorInstance(): Reflect
    {
        if (is_null(static::$instance)) {
            $instance = new static;

            static::$instance = $instance;
        }

        return static::$instance;
    }

    /**
     * Add a binding to the bindings array.
     *
     * @param mixed $abstract
     * @param mixed $concrete
     *
     * @return void
     */
    public function bind($abstract, $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Retrieve a binding.
     *
     * @param mixed $abstract
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public function getBinding($abstract)
    {
        if (is_callable($abstract) || !array_key_exists($abstract, $this->bindings)) {
            return $abstract;
        }

        $binding = $this->bindings[$abstract];

        if (is_a($binding, \Closure::class)) {
            return $this->resolve($binding);
        }

        return $binding;
    }

    /**
     * Test if a binding has been made against an abstract string.
     *
     * @param mixed $abstract
     *
     * @return bool
     */
    public function bindingExists($abstract)
    {
        return array_key_exists($abstract, $this->bindings);
    }

    /**
     * Add an alias to the aliases array.
     *
     * @param mixed $abstract
     * @param mixed $concrete
     *
     * @return void
     */
    public function alias($abstract, $concrete): void
    {
        $this->aliases[$abstract] = $concrete;
    }

    /**
     * Test whether an alias has been created and saved to this object.
     *
     * @param mixed $abstract
     *
     * @return bool
     */
    public function aliasExists($abstract): bool
    {
        return array_key_exists($abstract, $this->aliases);
    }

    /**
     * Get an alias out of this instance.
     *
     * @param mixed $abstract
     *
     * @return mixed
     */
    public function getAlias($abstract)
    {
        if (is_callable($abstract) || !array_key_exists($abstract, $this->aliases)) {
            return $abstract;
        }

        return $this->aliases[$abstract];
    }

    /**
     * Store an instance against an abstract string.
     *
     * @param mixed $abstract
     * @param mixed $instance
     *
     * @return void
     * @throws \ReflectionException
     */
    public function singleton($abstract, $instance): void
    {
        $this->instances[$abstract] = $this->resolve($instance);
    }

    /**
     * Test whether an instance has been created and saved to this object.
     *
     * @param string $abstract
     *
     * @return bool
     */
    public function singletonExists($abstract): bool
    {
        return array_key_exists($abstract, $this->instances);
    }

    /**
     * Get a current instance given its abstract caller.
     *
     * @param $abstract
     *
     * @return mixed
     */
    public function getSingleton($abstract)
    {
        if (!array_key_exists($abstract, $this->instances)) {
            return $abstract;
        }

        return  $this->instances[$abstract];
    }

    /**
     * Create a new instance of the target and return it to the caller.
     *
     * @param mixed $target
     * @param mixed ...$args
     *
     * @return mixed
     * @throws \ReflectionException
     */
    function resolve($target, ...$args)
    {
        // If a singleton already exists for this target, we can return it first
        // as by definition we don't want to re-initialise its instance.
        if(! is_callable($target) && $this->singletonExists($target)) {
            return $this->getSingleton($target);
        }

        // Check if there are any aliases registered for that target.
        $target = $this->getAlias($target);

        // Check if there are any bindings that are set up for that target.
        $target = $this->getBinding($target);

        $reflector = (!is_callable($target))
            ? new \ReflectionClass($target)
            : new \ReflectionFunction($target);

        if(is_a($reflector, \ReflectionClass::class)) {
            return $this->resolveClass($reflector, $args);
        }

        if(is_a($reflector, \ReflectionFunction::class)) {
            return $this->resolveFunction($reflector, $args);
        }
    }

    /**
     * Resolve a function.
     *
     * @param \ReflectionFunction $reflectionFunction
     * @param                     $args
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public function resolveFunction(\ReflectionFunction $reflectionFunction, $args)
    {
        $parameters = $reflectionFunction->getParameters();
        $dependencies = $this->getDependencies($parameters, $args);

        return $reflectionFunction->invokeArgs($dependencies);
    }

    /**
     * Resolve a given class.
     *
     * @param \ReflectionClass $reflectionClass
     * @param                  $args
     *
     * @return object
     * @throws \ReflectionException
     */
    public function resolveClass(\ReflectionClass $reflectionClass, $args)
    {
        // Check that the target class can be instantiated.
        // i.e is not of type Trait or Interface.
        if (!$reflectionClass->isInstantiable()) {
            throw new \ReflectionException("Target [{$reflectionClass->getName()}] is not instantiable");
        }

        // Now that we have determined that the target can be instantiated, we
        // can move on to extracting the constructor.
        $constructor = $reflectionClass->getConstructor();

        if (is_null($constructor)) {
            // The target has no __construct method - so we can return the
            // target as is.
            return $reflectionClass->newInstanceWithoutConstructor();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->getDependencies($parameters, $args);

        return $reflectionClass->newInstanceArgs($dependencies);
    }

    /**
     * Build up a list of dependencies for a given methods parameters
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
                : $this->resolve($dependency->name);
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

        throw new \ReflectionException("Failed to resolve unknown parameter.");
    }
}
