<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/classes/php_fast_cache.php';
require __DIR__ . '/classes/container.class.php';

class EvoFilter
{
    const CONTENT_TABLE = 'site_content';
    const TV_TABLE = 'site_tmplvars';
    const TVVALUES_TABLE = 'site_tmplvar_contentvalues';

    /**
     * @var DocumentParser
     */
    protected $modx;
    /**
     * ID каталога с которого осуществляется поиск
     *
     * @var int
     */
    protected $parent;
    /**
     * ID текущего ресурса
     *
     * @var int
     */
    protected $id;
    /**
     * Таблица ресурсов
     *
     * @var string
     */
    protected $contentTable;
    /**
     * Таблица TV параметров
     *
     * @var string
     */
    protected $tvTable;
    /**
     * Таблица значений TV параметров ресурсов
     *
     * @var string
     */
    protected $tvValuesTable;
    protected $request = [];
    protected $itemsCount = 0;
    protected $filteredItemsCount = 0;
    protected $childs = [];
    protected $chunks = [];
    /**
     * Массив TV параметров и их свойств созданный путем обработки шаблона формы
     *
     * @var array
     */
    protected $parsedTVs = [];
    /**
     * Массив настроек
     *
     * @var array
     */
    protected $config = [];

    /**
     * Конструктор
     *
     * @param DocumentParser $modx
     * @param array          $config
     */
    function __construct(DocumentParser & $modx, array $config = [])
    {
        $this->modx =& $modx;

        $this->config = [
            'form_tpl' => 'evoFiler_form', // Чанк шаблон формы поиска
            'tpl' => 'DLpost', // Чанк шаблон для DocLister
            'parent' => 10,
            'only_form' => false, // Показываем только форму без результатов
            'display' => 14, // Кол-во результатов на странице
            'prefix' => 'ef', // Префикс для TV параметров
            'request_prefix' => 'ef', // Префикс для TV параметров в запросе
            'tvList' => '', // Список ТВ параметров для DocLister
        ];

        if (!empty($config['form_tpl'])) {
            $this->config['form_tpl'] = $config['form_tpl'];
        }

        if (!empty($config['tpl'])) {
            $this->config['tpl'] = $config['tpl'];
        }

        if (!empty($config['parent'])) {
            $this->config['parent'] = (int) $config['parent'];
        }

        if (!empty($config['only_form'])) {
            $this->config['only_form'] = true;
        }

        if (!empty($config['display'])) {
            $this->config['display'] = (int) $config['display'];
        }

        if (!empty($config['prefix'])) {
            $this->config['prefix'] = $config['prefix'];
        }

        if (!empty($config['request_prefix'])) {
            $this->config['request_prefix'] = $config['request_prefix'];
        }

        if (!empty($config['tvList'])) {
            $this->config['tvList'] = $config['tvList'];
        }

        $this->parent = (int) $this->config['parent'];

        $this->id = (int) $modx->documentObject['id'];

        if ($this->id == $modx->getConfig('site_start')) {
            $this->id = $this->parent;
        }

        // Таблицы базы данных
        $this->contentTable = $modx->getFullTableName(self::CONTENT_TABLE);
        $this->tvTable = $modx->getFullTableName(self::TV_TABLE);
        $this->tvValuesTable = $modx->getFullTableName(self::TVVALUES_TABLE);

        // Настройки класса кэширования
        phpFastCache::$storage = 'files';
        phpFastCache::$path = MODX_BASE_PATH . 'assets/cache/';
        phpFastCache::$securityKey = 'custom';
    }

    /**
     * Генерация формы поиска и результатов
     * Данные помещаются в соответствующие плейсхолдеры
     *
     * [+prefix.form+]             : Форма поиска
     * [+prefix.result+]           : Результат работы DocLister
     * [+prefix.items_count+]      : Общее кол-во ресурсов
     * [+prefix.items_show_count+] : Кол-во найденных (отфильтрованных) ресурсов
     */
    public function process()
    {
        $this->parseTVs($this->getChunk($this->config['form_tpl']));

        if (!$this->config['only_form']) {
            $result = $this->modx->runSnippet('DocLister', [
                'display' => $this->config['display'],
                'dateSource' => 'pub_date',
                'documents' => $this->getFilteredResourceIds(),
                'tpl' => $this->config['tpl'],
                'paginate' => 'pages',
                'tvList' => $this->config['tvList'],
                'TplNextP' => 'dlnext',
                'TplPrevP' => 'dlprev',
                'TplPage' => 'dlpage',
                'TplCurrentPage' => 'dlcurpage',
                'TplWrapPaginate' => 'dlwrappag',
            ]);

            $this->setPlaceholder('result', $result);
            $this->setPlaceholder('items_count', $this->itemsCount);
            $this->setPlaceholder('items_show_count', $this->filteredItemsCount);
        }

        $this->setPlaceholder('form', $this->getForm());
    }

    /**
     * Генерация формы поиска
     *
     * @return string
     */
    protected function getForm()
    {
        $request = $this->getRequest();
        $params = [];

        foreach ($this->parsedTVs as $id => $tv) {
            $id = $this->config['request_prefix'] . $id;

            if (is_array($request->{$id})) {
                foreach ($tv as $k => $v) {
                    if (isset($request->{$id}[$k])) {
                        $params["tv:{$v['key']}"] = !empty($request->{$id}[$k]) ? e($request->{$id}[$k]) : '';
                    }
                }

                continue;
            }

            $output = '';

            if ($tv['type'] == 'select') {
                $output = $this->generateSelect($tv, $request->{$id});
            } elseif ($tv['type'] == 'checkbox') {
                $output = $this->generateCheckbox($tv, $request->{$id});
            } elseif ($tv['type'] == 'string') {
                $output = !empty($request->{$id}) ? e($request->{$id}) : '';
            } elseif ($tv['type'] == 'num') {
                $output = (int) $request->{$id} ? (int) $request->{$id} : '';
            }

            $params["tv:{$tv['key']}"] = $output;
        }

        // Категории
        $params['categories'] = '';
        foreach ($this->getCategories() as $id => $c) {
            $params['categories'] .= '<option value="' . $c['alias'] . '"' . ($this->id == $id ? ' selected' : '') . '>' . $c['title'] . '</option>';
        }

        $params['action'] = $this->modx->makeUrl($this->id);
        $params['request_prefix'] = $this->config['request_prefix'];

        $form = $this->parseChunk($this->config['form_tpl'], $params);

        return $form;
    }

    /**
     * Генерация массив TV параметров и их свойств
     * Все обработанные TV будут доступны для поиска
     *
     * -------------------------------------------------------------------------
     * Пример записи TV параметра в шаблоне формы
     * -------------------------------------------------------------------------
     *
     * Поиск по TV с ID 1 и генерирация списка возможных значений:
     * [+tv:{"id":1,"type":"select"}+]
     *
     * Пример:
     *
     * <select name="prefix1" class="styled">
     * <option value="">Выберите значение</option>
     * [+tv:{"id":1,"type":"select"}+]
     * </select>
     * -------------------------------------------------------------------------
     *
     * Поиск по TV с ID 2, отмечает флажок, если он указан в запросе:
     * [+tv:{"id":2,"type":"checkbox"}+]
     *
     * Пример:
     *
     * <input type="checkbox" name="prefix2" value="checked" [+tv:{"id":2,"type":"checkbox"}+]>
     * -------------------------------------------------------------------------
     *
     * В данном случае поиск осуществляется по числу (цене) меньше указанной
     * В поле value будет подставлено соотв. значение из запроса
     * [+tv:{"id":3,"type":"num","sign":"<"}+]
     *
     * Доступны след. знаки: < = > (меньше, равно, больше)
     *
     * Пример:
     * <input type="text" name="prefix8" value="[+tv:{"id":3,"type":"num","sign":"<"}+]">
     *
     * @param string $text
     *
     * @return array
     */
    protected function parseTVs($text = null)
    {
        if (empty($text) && !$this->parsedTVs) {
            return [];
        }

        if ($this->parsedTVs) {
            return $this->parsedTVs;
        }

        $matches = [];

        preg_match_all('/\[\+tv:(.*)\+\]/ui', $text, $matches);
        foreach ($matches[1] as $tv) {
            $value = json_decode($tv, true);
            if (!$value) {
                continue;
            }

            $value['key'] = $tv;

            // Обработка фильтрации по цене "ОТ" и "ДО"
            if ($value['type'] === 'price' && isset($value['order'])) {
                $this->parsedTVs[$value['id']][$value['order']] = $value;
            } // Все остальные
            else {
                $this->parsedTVs[$value['id']] = $value;
            }
        }

        return $this->parsedTVs;
    }

    /**
     * @param string $name
     * @param array  $params
     *
     * @return string
     */
    protected function parseChunk($name, $params = [], $start = '[+', $end = '+]')
    {
        if (!isset($this->chunks[$name])) {
            $this->chunks[$name] = $this->getChunk($name);
        }

        return $this->parseText($this->chunks[$name], $params, $start, $end);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function getChunk($name)
    {
        if (isset($this->chunks[$name])) {
            return $this->chunks[$name];
        }

        $this->chunks[$name] = $this->modx->getChunk($name);

        return $this->chunks[$name];
    }

    /**
     * @param string $string
     * @param array  $params
     *
     * @return mixed
     */
    protected function parseText($string, $params = [], $start = '[+', $end = '+]')
    {
        if (empty($string)) {
            return '';
        }

        foreach ($params as $key => $val) {
            $string = str_replace($start . $key . $end, $val, $string);
        }

        return $string;
    }

    /**
     * Получение списка элементов на основе значиний ТВ ресурсов
     *
     * @param int          $tvId ID TV параметра по которому будет сгенерирован список
     * @param array|string $ids  Список ID ресурсов для ограничение выборки
     *
     * @return array
     */
    protected function getListFromTvValues($tvId, $ids = null)
    {
        $key = "tv{$tvId}_{$this->id}_values";
        if ($values = $this->get($key)) {
            return $values;
        }

        $values = [];

        $sql = "SELECT DISTINCT value FROM {$this->tvValuesTable} WHERE (tmplvarid = {$tvId}) AND value != ''";

        if ($ids !== null) {
            if (is_array($ids)) {
                $ids = implode(',', $ids);
            }

            $sql .= " AND contentid IN ({$ids})";
        }

        $query = $this->modx->db->query($sql);

        while ($r = mysql_fetch_row($query)) {
            if ($r[0] = trim($r[0])) {
                $values[] = $r[0];
            }
        }

        sort($values);

        $this->put($key, $values);

        return $values;
    }

    /**
     * Получение списка элементов на основе возможных значений TV
     *
     * @param string $delimeter Разделитель возможных варинатов, по умолчанию '||'
     *
     * @return array
     */
    protected function getListFromTv($tvId, $delimeter = '||')
    {
        $key = "tv{$tvId}_default";
        if ($values = $this->get($key)) {
            return $values;
        }

        $values = [];

        $query = $this->modx->db->query("SELECT elements FROM {$this->tvTable} WHERE id = {$tvId}");

        $row = mysql_fetch_row($query);

        foreach (explode($delimeter, $row[0]) as $r) {
            $r = trim($r);
            if ($r) {
                $values[] = $r;
            }
        }

        $this->put($key, $values);

        return $values;
    }

    /**
     * Список категорий/Разделов
     *
     * @return array
     */
    public function getCategories()
    {
        if ($categories = $this->get('categories')) {
            return $categories;
        }

        $categories = [];

        $query = $this->modx->db->query("SELECT id, pagetitle, alias FROM {$this->contentTable} WHERE published = 1 AND deleted = 0 AND isfolder = 1 AND parent = {$this->parent}");

        while ($r = mysql_fetch_assoc($query)) {
            $categories[$r['id']] = ['alias' => $r['alias'], 'title' => $r['pagetitle']];
        }

        $this->put('categories', $categories);

        return $categories;
    }

    /**
     * Получение ID всех дочерних ресурсов каталога и запись в кэш
     */
    public function getCategoryChildIds()
    {
        if ($this->childs) {
            return $this->childs;
        }

        $key = "category_{$this->id}";
        // Получаем данные из кэша
        if (!$this->childs = $this->get($key)) {
            $where = 'published = 1 and deleted = 0 and isfolder = 0';

            $childs = $this->modx->getChildIds($this->id);
            $where .= ' and id IN (' . implode(',', $childs) . ')';

            $query = $this->modx->db->query("SELECT id FROM {$this->contentTable} WHERE {$where}");

            while ($r = mysql_fetch_assoc($query)) {
                $this->childs[] = $r['id'];
            }

            // Пишем данные в кэш
            $this->put($key, $this->childs, 3600);
        }

        $this->itemsCount = count($this->childs);
        // ID категорий
        $this->setPlaceholder('categories', implode(',', $this->childs));

        return $this->childs;
    }

    protected function getFilteredResourceIds()
    {
        $childIds = $this->getCategoryChildIds();
        $request = $this->getRequest();

        if ($request->isEmpty()) {
            $this->filteredItemsCount = $this->itemsCount;

            return $childIds;
        }

        $tvs = $this->parseTVs();

        $sql = "SELECT contentid FROM {$this->tvValuesTable} WHERE contentid IN (" . implode(',', $childIds) . ")";

        $count = $request->count();
        $i = 0;
        foreach ($request->getProperties() as $tv => $value) {
            $tv = $this->stringToInt($tv);
            $sql .= $i ? ' or ' : ' and ';

            // Выборка по цене - ОТ и ДО
            if (is_array($value) && is_array($tvs[$tv])) {
                if (isset($tvs[$tv]['from']) && isset($tvs[$tv]['to']) && $value['from'] && $value['to']) {
                    // Если цена "ОТ" больше или равна цены "ДО"
                    if ($value['from'] >= $value['to']) {
                        $value['from'] = 0;
                    }

                    $sql .= "(tmplvarid = {$tv} and value BETWEEN {$value['from']} and {$value['to']})";
                } // Если указана только цена "ОТ"
                elseif (isset($tvs[$tv]['from']) && $value['from']) {
                    $sql .= "(tmplvarid = {$tv} and value > {$value['from']})";
                } // Если указана только цена "ДО"
                elseif (isset($tvs[$tv]['to']) && $value['to']) {
                    $sql .= "(tmplvarid = {$tv} and value < {$value['to']})";
                }

                continue;
            }

            // Выборка по другим типам ТВ
            switch ($tvs[$tv]['type']) {
                // Флильтрация по чекбоксу
                case 'checkbox':
                    $sql .= "(tmplvarid = {$tv} and value != '')";
                    break;
                // Фильтр по числу
                case 'num':
                    if (!isset($tvs[$tv]['sign'])) {
                        $tvs[$tv]['sign'] = '<';
                    }
                    $sign = preg_match('/^[<|=|>]$/', $tvs[$tv]['sign']);
                    $sql .= "(tmplvarid = {$tv} and value " . ($sign ? $tvs[$tv]['sign'] : '<') . " " . intval($value) . ")";
                    break;
                // Фильтрация по выбранному значению из списка или введенному значению
                case 'select':
                case 'string':
                    $sql .= "(tmplvarid = {$tv} and value = '{$value}')";
                    break;
            }
            $i++;
        }

        $sql .= " GROUP BY contentid HAVING count(*) = {$count}";

        $key = md5($sql);

        if (!$ids = $this->get($key)) {
            $query = $this->modx->db->query($sql);

            $ids = [];
            while ($r = mysql_fetch_row($query)) {
                $ids[] = $r[0];
            }

            $this->put($key, $ids);
        }

        $this->filteredItemsCount = count($ids);
        // ID найденный документов
        $this->setPlaceholder('ids', implode(',', $ids));

        return $ids;
    }

    /**
     * Подготовка и обработка запроса
     *
     * @param string $prefix По умолчанию 'ef'
     *
     * @return Container
     */
    protected function getRequest($prefix = null)
    {
        if ($this->request) {
            return $this->request;
        }

        $request = $_REQUEST;

        if ($prefix === null) {
            $prefix = $this->config['request_prefix'];
        }

        // Обходим массив с запросом
        foreach ($request as $tv => $value) {
            if (strpos($tv, $prefix) !== 0) {
                continue;
            }

            // ID TV параметра
            $tv = $this->stringToInt($tv);
            // Проверяем разрешен ли поиск по этому TV
            if (!isset($this->parsedTVs[$tv])) {
                continue;
            }

            $this->escapeTVValue($value, $this->parsedTVs[$tv]);
            if (empty($value)) {
                continue;
            }

            $this->request[$prefix . $tv] = $value;
        }

        ksort($this->request);

        $this->request = new Container($this->request);

        return $this->request;
    }

    protected function getCacheKey()
    {
        return $this->id . '_' . md5(implode(',', $this->getRequest()));
    }

    /**
     * Получение ID TV параметра из строки
     * По факту простая обработка строки регулярным выражением,
     * удаляется всё кроме цифр
     *
     * @param string $value
     *
     * @return int
     */
    protected function stringToInt($value)
    {
        return (int) preg_replace('/[^\d]+/', '', (string) $value);
    }

    /**
     * Генерация элементов выпадающего списка
     *
     * @param array  $tv
     * @param string $value
     *
     * @return string
     */
    protected function generateSelect(array $tv, $value = '')
    {
        if (!empty($tv['delimeter'])) {
            $list = $this->getListFromTv($tv['id'], $tv['delimeter']);
        } else {
            $childs = implode(',', $this->getCategoryChildIds());
            $list = $this->getListFromTvValues($tv['id'], $childs);
        }

        $output = '';
        foreach ($list as $k => $v) {
            $output .= '<option value="' . $v . '"' . ($value == $v ? ' selected' : '') . '>' . $v . '</option>';
        }

        return $output;
    }

    protected function generateNum()
    {
    }

    protected function generateCheckbox(array $tv, $value = '')
    {
        return !empty($value) ? 'checked' : '';
    }

    protected function generateString(array $tv, $value = '')
    {
    }

    protected function generatePrice(array $tv, $value = '')
    {
    }

    protected function escapeTVValue(& $value, $tv)
    {
        if (!is_array($value)) {
            $this->escape($value);
        } else {
            foreach ($value as $k => & $v) {
                if (isset($tv['from']) || isset($tv['to'])) {
                    $v = (int) $v;
                }
                if (empty($v)) {
                    unset($value[$k]);
                } else {
                    $this->escape($v);
                }
            }
        }
    }

    protected function escape(& $value)
    {
        if (is_numeric($value)) {
            $value = (int) $value;
        } else {
            $value = $this->modx->db->escape((string) $value);
        }
    }

    /**
     * Установка плейсхолдера с соотв. префиксом, по умолчанию префикс 'ef.'
     *
     * @param string $name  Имя
     * @param string $value Значение
     */
    protected function setPlaceholder($name, $value)
    {
        if (!is_scalar($value)) {
            return;
        }

        $this->modx->setPlaceholder($this->config['prefix'] . '.' . $name, $value);
    }

    /**
     * Запись данных в кэш
     *
     * @param      $key
     * @param      $value
     * @param int  $seconds По умолчанию 12 часов
     * @param bool $skip_if_existing
     *
     * @return array|bool
     */
    protected function put($key, $value, $seconds = 43200, $skip_if_existing = false)
    {
        return phpFastCache::set($key, $value, $seconds, $skip_if_existing);
    }

    /**
     * Получение данных из кэша
     *
     * @param $key
     *
     * @return mixed|null
     */
    protected function get($key)
    {
        return phpFastCache::get($key);
    }

    /**
     * Очистка кэша
     * Возвращает кол-во удаленных файлов
     *
     * @return int|null
     */
    public function cleanCache()
    {
        return phpFastCache::cleanup();
    }

}
