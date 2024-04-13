<?php
/**
 *
 * Author: feiyu
 * Date: 2024/4/13
 * Time: 10:36
 * @return
 */

namespace Teanxo\ThinkSearch;

use Teanxo\ThinkSearch\Exception\SearchTypeException;

class SearchRule
{
    private array $results = [];

    private array $sqlResults = [];

    private array $mapping;

    private mixed $value;

    const CallFunctionNames = [
        SearchType::Equals => 'equals',
        SearchType::Like => 'like',
        SearchType::Between => 'between',
        SearchType::BetweenTime => 'betweenTime',
        SearchType::In => 'in',
        SearchType::NotIn => 'notIn'
    ];


    public function handler()
    {
        if (!in_array($this->mapping['selectType'], array_keys(self::CallFunctionNames))){
            throw new SearchTypeException("No valid matcher found");
        }

        $refMethod = new \ReflectionMethod(static::class, self::CallFunctionNames[$this->mapping['selectType']]);
        $refMethod->invoke($this);

        $this->sqlRecords();

        return $this;
    }

    private function equals(): void
    {
        $this->value = $this->formatValue();
        $this->results[$this->columName()] = $this->value;
    }

    private function like(): void
    {
        $this->value = $this->formatValue();
        $this->results[] = [$this->columName(), 'LIKE', "%{$this->value}%"];
    }

    private function between(): void
    {
        $this->value = $this->formatValue();
        is_string($this->value) && $this->value = explode(',', $this->value);

        $this->results[] = [$this->columName(), 'between', $this->value];
    }

    private function betweenTime(): void
    {
        $this->value = $this->formatValue();
        is_string($this->value) && $this->value = explode(',', $this->value);

        $this->value = array_map(
            fn ($v, $k) => strtotime(strlen($v) == 10 ? $v.($k === 0 ? ' 00:00:00' : ' 23:59:59') : $v),
            array_values($this->value),
            array_keys($this->value)
        );

        $this->results[] = [$this->columName(), 'between', $this->value];
    }


    public function in(): void
    {
        $this->value = $this->formatValue();
        is_string($this->value) && $value = explode(',', $this->value);
        $this->results[] = [$this->columName(), 'in', $this->value];
    }

    public function notIn(): void
    {
        $this->value = $this->formatValue();
        is_string($this->value) && $this->value = explode(',', $this->value);

        $this->results[] = [$this->columName(), 'not in', $this->value];
    }

    public function getResult(): array
    {
        return $this->results;
    }

    public function getSqlResult(): array
    {
        return $this->sqlResults;
    }

    public function sqlRecords()
    {
        $columnName = $this->columName();
        $value = is_string($this->value) ? "'{$this->value}'" : $this->value;

        $selectTypeVal = $this->mapping['selectType'];
        $selectTypeString = SearchType::toString($selectTypeVal);

        if (in_array($selectTypeVal, [SearchType::Between, SearchType::BetweenTime])) {
            $value = implode(' AND ', $value);
        } else if ($selectTypeVal === SearchType::In) {
            $value = '(' . implode(',', $value) . ')';
        } else {
            is_array($value) && $value = implode(',', $value);
        }

        if (strpos($columnName, '|') !== false) {
            $sqls = array_map(fn($column) => " {$column} {$selectTypeString} {$value} ", explode('|', $columnName));
            $this->sqlResults[] = '(' . implode(' OR ', $sqls) . ')';
        } else {
            $this->sqlResults[] = "{$columnName} {$selectTypeString} {$value}";
        }
    }

    public function setMapping(array $mapping): SearchRule
    {
        $this->mapping = $mapping;
        return $this;
    }

    public function setValue(mixed $value): SearchRule
    {
        $this->value = $value;
        return $this;
    }

    private function columName()
    {
        if ($this->mapping['nameFormatter']) {
            return call_user_func($this->mapping['nameFormatter'], $this->value);
        }
        return !empty($this->mapping['aliasName']) ? $this->mapping['aliasName'] : $this->mapping['columnName'];
    }

    private function formatValue()
    {
        $value = $this->mapping['defaultValue'] ?? $this->value;
        if ($this->mapping['valueFormater']) {
            $value = call_user_func($this->mapping['valueFormater'], $value, $this->mapping['columnName'], $this);
        }
        return $value;
    }
}