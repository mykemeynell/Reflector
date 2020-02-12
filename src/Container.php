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
        if (!$this->bindingExists($abstract)) {
            return $abstract;
        }

        $binding = $this->bindings[$abstract];

        if (is_callable($abstract)) {
            return $this->make($binding, self::getContainerInstance());
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
        $this->instances[$abstract] = $this->make($concrete, self::getContainerInstance());
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
     * @param mixed ...$parameters
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public function make($target, ...$parameters)
    {
        if (!is_callable($target) && $this->singletonExists($target)) {
            // The target is not callable and has a singleton associated -
            // so we can return the singleton instance.
            return $this->instance($target);
        }

        if (!is_callable($target)) {
            // Check if there are any bindings that are set up against the given target.
            $target = $this->binding($target);
        }

        // Determine if what is being reflected on is should be treated as
        // a function/helper or object.
        if (is_callable($target)) {
            return $target(static::getContainerInstance());
        }

        return DI::resolve($target, $parameters);
    }
}
