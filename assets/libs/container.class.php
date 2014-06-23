<?php

class Container {
    protected $properties;

    function __construct(array $data = null)
    {
        $this->properties = $data;
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function get($name, $default = null)
    {
        return isset($this->properties[$name]) ? $this->properties[$name] : $default;
    }

    public function __set($name, $value)
    {
        $this->properties[$name] = $value;
    }

    public function __isset($name)
    {
        return isset($this->properties[$name]);
    }

    public function __unset($name)
    {
        unset($this->properties[$name]);
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function count()
    {
        return count($this->properties);
    }

    public function isEmpty()
    {
        return ! $this->count();
    }
}
