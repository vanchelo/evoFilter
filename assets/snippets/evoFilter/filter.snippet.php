<?php
defined('MODX_BASE_PATH') or die;

include MODX_BASE_PATH . 'assets/snippets/evoFilter/filter.class.php';

$params = is_array($modx->event->params) ? $modx->event->params : array();

$filter = new Filter($modx, $params);

$filter->process();

return '';
