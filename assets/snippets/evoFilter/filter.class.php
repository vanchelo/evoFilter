<?php

require MODX_BASE_PATH . 'assets/libs/helpers.php';
require MODX_BASE_PATH . 'assets/libs/php_fast_cache.php';
require MODX_BASE_PATH . 'assets/libs/container.class.php';

class Filter
{
    const CONTENT_TABLE = 'site_content';
    const TV_TABLE = 'site_tmplvars';
    const TVVALUES_TABLE = 'site_tmplvar_contentvalues';

    /**
     * @var DocumentParser
     */
    protected $modx;
    /**
     * ID каталога
     *
     * @var int
     */
    protected $parent;
    /**
     * ID текущего ресурса
     * @var int
     */
    protected $id;
    protected $contentTable;
    protected $tvTable;
    protected $tvValuesTable;
    protected $request = array();
    protected $itemsCount = 0;
    protected $filteredItemsCount = 0;
    protected $childs = array();
    protected $chunks = array();
    protected $parsedTVs = array();
    protected $config = array();

    /**
     * Конструктор
     *
     * @param DocumentParser $modx
     * @param array $config
     */
    function __construct(DocumentParser & $modx, array $config = array())
    {
        $this->modx =& $modx;

        $this->config = array(
            'form_tpl'  => 'new_filter_form',
            'tpl'       => 'DLpost',
            'parent'    => 49,
            'only_form' => false,
            'display'   => 14,
        );

        if (!empty($config['form_tpl'])) $this->config['form_tpl'] = $config['form_tpl'];
        if (!empty($config['tpl'])) $this->config['tpl'] = $config['tpl'];
        if (!empty($config['parent'])) $this->config['parent'] = $config['parent'];
        if (!empty($config['only_form'])) $this->config['only_form'] = true;
        if (!empty($config['display'])) $this->config['display'] = (int) $config['display'];

        $this->parent = (int) $this->config['parent'];

        $this->id = (int) $modx->documentObject['id'];

        if ($this->id == $modx->getConfig('site_start'))
        {
            $this->id = $this->parent;
        }

        $this->config['rules'] = array(
            'sign'  => array('<','>','='),
            'types' => array('checkbox','num','select'),
        );

        // Таблицы базы данных
        $this->contentTable = $modx->getFullTableName(self::CONTENT_TABLE);
        $this->tvTable = $modx->getFullTableName(self::TV_TABLE);
        $this->tvValuesTable = $modx->getFullTableName(self::TVVALUES_TABLE);

        // Настройки кэшера
        phpFastCache::$storage = 'files';
        phpFastCache::$path = MODX_BASE_PATH . 'assets/cache/';
        phpFastCache::$securityKey = 'custom';
    }

    /**
     *
     */
    public function process()
    {
        $this->parseTVs($this->getChunk($this->config['form_tpl']));

        if ( ! $this->config['only_form'])
        {
            $result = $this->modx->runSnippet('DocLister', array(
                'display'         => $this->config['display'],
                'dateSource'      => 'pub_date',
                'documents'       => $this->getFilteredResourceIds(),
                'tpl'             => $this->config['tpl'],
                'paginate'        => 'pages',
                'tvList'          => 'image-cat,kind,komnat,price,price_ed',
                'TplNextP'        => 'dlnext',
                'TplPrevP'        => 'dlprev',
                'TplPage'         => 'dlpage',
                'TplCurrentPage'  => 'dlcurpage',
                'TplWrapPaginate' => 'dlwrappag',
            ));

            $this->modx->setPlaceholder('af.result', $result);
            $this->modx->setPlaceholder('af.items_count', $this->itemsCount);
            $this->modx->setPlaceholder('af.items_show_count', $this->filteredItemsCount);
        }

        $this->modx->setPlaceholder('af.form', $this->getForm());
    }

    /**
     * Генерация формы поиска
     *
     * @return mixed
     */
    protected function getForm()
    {
        $request = $this->getRequest();
        $params = array();

        $childs = implode(',', $this->getCategoryChildIds());

        foreach ($this->parsedTVs as $tv)
        {
            $id = "i{$tv['id']}";
            $output = '';
            if ($tv['type'] == 'select')
            {
                if ($tv['delimeter']) $list = $this->getListFromTv($tv['id'], $tv['delimeter']);
                else $list = $this->getListFromTvValues($tv['id'], $childs);

                foreach ($list as $k => $l) {
                    $output .= '<option value="'.$l.'"' . ($request->{$id} == $l ? ' selected' : '') . '>'.$l.'</option>';
                }
            }
            elseif ($tv['type'] == 'checkbox')
            {
                $output = !empty($request->{$id}) ? 'checked' : '';
            }
            elseif ($tv['type'] == 'num')
            {
                $output = (int) $request->{$id} ? (int) $request->{$id} : '';
            }

            $params["tv:{$tv['key']}"] = $output;
        }

        // Категории
        $params['categories'] = '';
        foreach ($this->getCategories() as $id => $c) {
            $params['categories'] .= '<option value="'.$c['alias'].'"'.($this->id == $id ? ' selected' : '').'>'.$c['title'].'</option>';
        }

        $params['action'] = $this->modx->makeUrl($this->id);

        $form = $this->parseChunk($this->config['form_tpl'], $params);

        return $form;
    }

    /**
     * @param $text
     * @return array
     */
    protected function parseTVs($text = null)
    {
        if (empty($text) && ! $this->parsedTVs)
        {
            return array();
        }

        if ($this->parsedTVs) return $this->parsedTVs;

        $matches = array();

        preg_match_all('/\[\+tv:(.*)\+\]/ui', $text, $matches);
        foreach ($matches[1] as $tv) {
            $value = json_decode($tv, true);
            if ( ! $value) continue;

            $value['key'] = $tv;

            $this->parsedTVs[$value['id']] = $value;
        }

        return $this->parsedTVs;
    }

    /**
     * @param $name
     * @param array $params
     * @return mixed
     */
    protected function parseChunk($name, $params = array(), $start = '[+', $end = '+]')
    {
        if ( ! isset($this->chunks[$name]))
        {
            $this->chunks[$name] = $this->getChunk($name);
        }

        return $this->parseText($this->chunks[$name], $params, $start, $end);
    }

    /**
     * @param $name
     * @return mixed
     */
    protected function getChunk($name)
    {
        if (isset($this->chunks[$name]))
        {
            return $this->chunks[$name];
        }

        $this->chunks[$name] =  $this->modx->getChunk($name);

        return $this->chunks[$name];
    }

    /**
     * @param $string
     * @param array $params
     * @return mixed
     */
    protected function parseText($string, $params = array(), $start = '[+', $end = '+]')
    {
        if (empty($string)) return '';

        foreach ($params as $key => $val) {
            $string = str_replace($start . $key . $end, $val, $string);
        }

        return $string;

    }

    /**
     * Получение списка элементов на основе значиний ТВ ресурсов
     *
     * @param $tvId
     * @param null $ids
     * @return array|mixed|null
     */
    protected function getListFromTvValues($tvId, $ids = null)
    {
        $key = "tv{$tvId}_{$this->id}_values";
        if ($values = $this->get($key)) return $values;

        $values = array();

        $sql = "SELECT DISTINCT value FROM {$this->tvValuesTable} WHERE (tmplvarid = {$tvId}) AND value != ''";

        if ($ids !== null)
        {
            if (is_array($ids)) $ids = implode(',', $ids);

            $sql .= " AND contentid IN ({$ids})";
        }

        $query = $this->modx->db->query($sql);

        while ($r = mysql_fetch_row($query)) {
            if ($r[0] = trim($r[0])) $values[] = $r[0];
        }

        sort($values);

        $this->put($key, $values);

        return $values;
    }

    /**
     * Получение списка элементов на основе возможных значений ТВ
     *
     * @return array
     */
    protected function getListFromTv($tvId, $delimeter = '||')
    {
        $key = "tv{$tvId}_default";
        if ($values = $this->get($key)) return $values;

        $values = array();

        $query = $this->modx->db->query("SELECT elements FROM {$this->tvTable} WHERE id = {$tvId}");

        $row = mysql_fetch_row($query);

        foreach (explode($delimeter, $row[0]) as $r) {
            $r = trim($r);
            if ($r) $values[] = $r;
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
        if ($categories = $this->get('categories')) return $categories;

        $categories = array();

        $query = $this->modx->db->query("SELECT id, pagetitle, alias FROM {$this->contentTable} WHERE published = 1 AND deleted = 0 AND isfolder = 1 AND parent = {$this->parent}");

        while ($r = mysql_fetch_assoc($query)) {
            $categories[$r['id']] = array('alias' => $r['alias'], 'title' => $r['pagetitle']);
        }

        $this->put('categories', $categories);

        return $categories;
    }

    /**
     * Получение ID всех дочерних ресурсов каталога и запись в кэш
     */
    public function getCategoryChildIds()
    {
        if ($this->childs)
        {
            return $this->childs;
        }

        $key = "category_{$this->id}";
        // Получаем данные из кэша
        if ( ! $this->childs = $this->get($key)) {
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

        return $this->childs;
    }

    protected function getFilteredResourceIds()
    {
        $childIds = $this->getCategoryChildIds();
        $request = $this->getRequest();

        if ($request->isEmpty())
        {
            $this->filteredItemsCount = $this->itemsCount;

            return $childIds;
        }

        $tvs = $this->parseTVs();

        $sql = "SELECT contentid FROM {$this->tvValuesTable} WHERE contentid IN (".implode(',', $childIds).")";

        $count = $request->count();
        $i = 0;
        foreach ($request->getProperties() as $tv => $value)
        {
            $tv = $this->getTVId($tv);
            $sql .= $i ? ' or ' : ' and ';
            /*// Чекбокс
            if ($value == 'checked') $sql .= "(tmplvarid = {$tv} and value != '')";
            // Цена
            elseif ($tv == 8)
            // Остальные
            else $sql .= "(tmplvarid = {$tv} and value = '{$value}')";*/

            switch ($tvs[$tv]['type']) {
                case 'checkbox':
                    $sql .= "(tmplvarid = {$tv} and value != '')";
                    break;
                case 'num':
                    if ( ! isset($tvs[$tv]['sign'])) $tvs[$tv]['sign'] = '<';
                    $sign = preg_match('/^[<|=|>]$/', $tvs[$tv]['sign']);
                    $sql .= "(tmplvarid = {$tv} and value " . ($sign ? $tvs[$tv]['sign'] : '<') . " ".intval($value).")";
                    break;
                case 'select':
                    $sql .= "(tmplvarid = {$tv} and value = '{$value}')";
                    break;
            }
            $i++;
        }

        $sql .= " GROUP BY contentid HAVING count(*) = {$count}";

        $key = md5($sql);

        if ( ! $ids = $this->get($key)) {
            $query = $this->modx->db->query($sql);

            $ids = array();
            while ($r = mysql_fetch_row($query)) {
                $ids[] = $r[0];
            }

            $this->put($key, $ids);
        }

        $this->filteredItemsCount = count($ids);

        return $ids;
    }

    protected function getRequest($prefix = 'i')
    {
        if ($this->request) return $this->request;

        $request = $_REQUEST;

        // Обходим массив с запросом
        foreach ($request as $tv => $value)
        {
            if (strpos($tv, $prefix) !== 0) continue;

            // ID TV параметра
            $tv = $this->getTVId($tv);
            $value = $this->modx->db->escape((string) $value);

            // Проверяем разрешен ли поиск по этому TV
            if ( ! isset($this->parsedTVs[$tv]) || empty($value)) continue;

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

    protected function getTVId($value)
    {
        return (int) preg_replace('/[^\d]+/', '', $value);
    }

    protected function generateSelect()
    {

    }

    protected function generateNum()
    {

    }

    protected function generateCheckbox()
    {

    }

    /**
     * Запись данных в кэш
     *
     * @param $key
     * @param $value
     * @param int $seconds
     * @param bool $skip_if_existing
     * @return array|bool
     */
    protected function put($key, $value, $seconds = 3600, $skip_if_existing = false)
    {
        return phpFastCache::set($key, $value, $seconds, $skip_if_existing);
    }

    /**
     * Получение данных из кэша
     *
     * @param $key
     * @return mixed|null
     */
    protected function get($key)
    {
        return phpFastCache::get($key);
    }

}
