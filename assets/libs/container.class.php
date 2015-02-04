<?php

class Container implements ArrayAccess, Countable {
    protected $properties;

    public function __construct(array $properties = array())
    {
        foreach ($properties as $key => $value)
        {
            $this->properties[$key] = $value;
        }
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

    public function toArray()
    {
        return $this->getProperties();
    }

    public function count()
    {
        return count($this->properties);
    }

    public function isEmpty()
    {
        return ! $this->count();
    }

    function __toString()
    {
        return json_encode($this->properties);
    }

    public function offsetExists($offset)
    {
        return isset($this->{$offset});
    }

    public function offsetGet($offset)
    {
        return $this->{$offset};
    }

    public function offsetSet($offset, $value)
    {
        $this->{$offset} = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->{$offset});
    }
}
