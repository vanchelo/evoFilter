<?php

class Helpers {

  public static function getInstance()
  {
    static $instance = null;
    if (null === $instance) {
      $instance = new self;
    }

    return $instance;
  }

  /**
   * Dump
   *
   * @param  mixed  $value
   * @return void
   */
  public function dump($value) {
    echo '<pre>';
    print_r($value);
    echo '</pre>';
  }

  /**
   * Convert an HTML string to entities.
   *
   * @param  string  $value
   * @return string
   */
  public function entities($value)
  {
    return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
  }

  /**
   * Очистка
   *
   * @param string $value
   * @param bool  $stripTags Удалять или нет теги
   * @return string
   */
  public function clean($value, $stripTags = true)
  {
    if ($value === null)
    {
      return null;
    }

    if ($stripTags)
    {
      $value = strip_tags((string) $value);
    }

    return $this->entities(trim($value));
  }

  /**
   * Protected constructor to prevent creating a new instance of the
   * *Singleton* via the `new` operator from outside of this class.
   */
  protected function __construct() {}

  /**
   * Private clone method to prevent cloning of the instance of the
   * *Singleton* instance.
   *
   * @return void
   */
  private function __clone() {}

  /**
   * Private unserialize method to prevent unserializing of the *Singleton*
   * instance.
   *
   * @return void
   */
  private function __wakeup() {}

}

if ( ! function_exists('dump')) {
  /**
   * @param null $value
   */
  function dump($value = null) {
    helper()->dump($value);
  }
}

if ( ! function_exists('e')) {
  /**
   * @param null $value
   * @return string
   */
  function e($value = null) {
    return helper()->entities($value);
  }
}

if ( ! function_exists('clean_val')) {
  /**
   * @param null $value
   * @param bool $stripTags
   * @return string
   */
  function clean_val($value = null, $stripTags = true) {
    return helper()->clean($value, $stripTags);
  }
}


if ( ! function_exists('helper')) {
  /**
   * @return Helpers
   */
  function helper() {
    return Helpers::getInstance();
  }
}
