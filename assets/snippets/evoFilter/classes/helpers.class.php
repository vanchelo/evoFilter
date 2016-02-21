<?php
/**
 * Copyright (c) Brehznev Ivan <brehznev.ivan@yahoo.com>
 */

/**
 * Class Helpers
 */
class Helpers
{
    /**
     * @var self
     */
    protected static $instance;

    /**
     * @return self
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Dump
     *
     * @param mixed $value
     *
     * @return void
     */
    public function dump($value)
    {
        echo '<pre>';
        print_r($value);
        echo '</pre>';
    }

    /**
     * Convert an HTML string to entities.
     *
     * @param string $value
     *
     * @return string
     */
    public function entities($value)
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * Clean string and strip tags
     *
     * @param string $value
     * @param bool   $stripTags Remvoe tags or not
     *
     * @return string
     */
    public function clean($value, $stripTags = true)
    {
        if (!is_string($value)) {
            return '';
        }

        if ($stripTags) {
            $value = strip_tags((string) $value);
        }

        return $this->entities(trim($value));
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    private function __construct()
    {
    }

    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Private unserialize method to prevent unserializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    private function __wakeup()
    {
    }
}
