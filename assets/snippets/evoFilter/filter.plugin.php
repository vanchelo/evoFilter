//<?php

/** @var DocumentParser $modx */

if ($modx->Event->name === 'OnSiteRefresh') {
    require MODX_BASE_PATH . 'assets/snippets/filter.class.php';

    $filter = new EvoFilter($modx);

    $output = '<br><br>';
    $output .= 'Очистка кэша <b>evoFilter</b>: ';
    $total = $filter->cleanCache();
    $output .= 'Удалено файлов: <b>' . $total . '</b>';
    echo $output;
}
