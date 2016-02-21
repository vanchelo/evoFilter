<?php

/** @var DocumentParser $modx */
defined('MODX_BASE_PATH') or die;

require MODX_BASE_PATH . 'assets/snippets/evoFilter/filter.class.php';

$params = is_array($modx->event->params) ? $modx->event->params : array();

$filter = new EvoFilter($modx, $params);

$filter->process();

return '';
