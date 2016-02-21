<?php
/**
 * Copyright (c) Brehznev Ivan <brehznev.ivan@yahoo.com>
 */

if (!function_exists('dump')) {
    /**
     * @param mixed $value
     */
    function dump($value = null)
    {
        helper()->dump($value);
    }
}

if (!function_exists('e')) {
    /**
     * @param string $value
     *
     * @return string
     */
    function e($value = null)
    {
        return helper()->entities($value);
    }
}

if (!function_exists('clean_val')) {
    /**
     * @param string $value
     * @param bool   $stripTags
     *
     * @return string
     */
    function clean_val($value = null, $stripTags = true)
    {
        return helper()->clean($value, $stripTags);
    }
}

if (!function_exists('helper')) {
    /**
     * @return Helpers
     */
    function helper()
    {
        return Helpers::instance();
    }
}
