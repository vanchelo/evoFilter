<?php
/**
 * Copyright (c) Brehznev Ivan <brehznev.ivan@yahoo.com>
 */

/**
 * Class Container
 */
class Container implements ArrayAccess, Countable
{
    /**
     * @var
     */
    protected $properties;

    /**
     * Container constructor.
     *
     * @param array $properties
     */
    public function __construct(array $properties = [])
    {
        foreach ($properties as $key => $value) {
            $this->properties[$key] = $value;
        }
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($name, $default = null)
    {
        return isset($this->properties[$name]) ? $this->properties[$name] : $default;
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value)
    {
        $this->properties[$name] = $value;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->properties[$name]);
    }

    /**
     * @param string $name
     */
    public function __unset($name)
    {
        unset($this->properties[$name]);
    }

    /**
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->getProperties();
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->properties);
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return !$this->count();
    }

    /**
     * @return string
     */
    function __toString()
    {
        return json_encode($this->properties);
    }

    /**
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->{$offset});
    }

    /**
     * @param string $offset
     *
     * @return null
     */
    public function offsetGet($offset)
    {
        return $this->{$offset};
    }

    /**
     * @param string $offset
     * @param mixed  $value
     */
    public function offsetSet($offset, $value)
    {
        $this->{$offset} = $value;
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->{$offset});
    }
}
