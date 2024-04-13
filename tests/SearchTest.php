<?php
/**
 *
 * Author: feiyu
 * Date: 2024/4/13
 * Time: 11:04
 * @return
 */

namespace Feiyu\tests;

use Teanxo\ThinkSearch\SearchType;
use Teanxo\ThinkSearch\ThinkSearch;
use PHPUnit\Framework\TestCase;

class SearchTest extends TestCase
{
    public function testSearch()
    {
        $requestParams = [
            "type" => "2",
            "keyword" => "13277777777",
            "create_time" => "2024-01-01,2024-03-01",
            "level" => "1"
        ];

        $searchSql = ThinkSearch::getInstance($requestParams)
            ->addMapping(
                columnName: "type",
                nameFormatter: fn ($val) => match((int)$val){
                    1 => "a.email",
                    2 => "a.phone"
                },
                filter: fn ($v) => $v > 1,
                desc: "注册类型"
            )
            ->addMapping(columnName: "keyword", aliasName: "u.username|u.phone|u.account", selectType: SearchType::Like,desc: "关键信息")
            ->addMapping(columnName: "create_time", aliasName: "u.create_time", selectType: SearchType::BetweenTime, desc: "用户创建时间")
            ->addMapping(
                columnName: "level",
                aliasName: "u.level",
                valueFormater: fn ($val) => match((int)$val){
                    1 => [1,2,3],
                    2 => [4,5,6]
                },
                desc: "身份类型",
                selectType: SearchType::In
            )
            ->buildSql(isAutoWhere: true);
        var_dump($searchSql);
    }
}