<?php
namespace Teanxo\ThinkSearch;

class ThinkSearch
{
    protected static $_instance;

    protected array $mappers;

    private array $params;

    private SearchRule $searchRule;

    public function __construct()
    {
        $this->searchRule = new SearchRule();
    }

    public static function getInstance(array $params): ThinkSearch
    {
        if (!isset(self::$_instance)){
            self::$_instance = new static();
        }
        self::$_instance->params = $params;
        return self::$_instance;
    }

    public function addMapping(
        string          $columnName,
        ?string         $aliasName = "",
        ?int            $selectType = SearchType::Equals,
        ?\Closure       $valueFormater = null,
        ?\Closure       $filter = null,
        ?\Closure       $nameFormatter = null,
        ?string         $desc = ""
    )
    {
        $this->mappers[$columnName] = compact('columnName', 'aliasName',
            'desc', 'selectType', 'filter', 'valueFormater', 'nameFormatter');
        return $this;
    }

    public function build(): array
    {
        $params = array_filter(
            $this->params,
            fn ($v, $k) => in_array($k, array_keys($this->mappers)) && (isset($v) && $v !== '' && $v !== []),
            ARRAY_FILTER_USE_BOTH);

        foreach($params as $k => $v){
            if (!empty($this->mappers[$k]['filter']) && !(call_user_func($this->mappers[$k]['filter'], $v)) ){
                continue;
            }
            $this->searchRule->setMapping($this->mappers[$k])
                ->setValue($v)
                ->handler();
        }

        return $this->searchRule->getResult();
    }

    public function buildSql(
        bool $isBuild = true,
        bool $isAutoWhere = false,
        bool $isAutoAnd = false
    ): string
    {
        $isBuild && $this->build();
        $sql = $this->searchRule->getSqlResult();
        $sql = ($isAutoWhere && !empty($sql) ? 'WHERE ' : '') . implode(' AND ', $sql);
        return !empty($sql) && $isAutoAnd ? 'AND '.$sql : $sql;
    }
}