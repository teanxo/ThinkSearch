<?php
/**
 *
 * Author: feiyu
 * Date: 2024/4/13
 * Time: 10:22
 * @return
 */

namespace Teanxo\ThinkSearch;

class SearchType
{
    const Equals = 1;

    const Like = 2;

    const Between = 3;

    const BetweenTime = 4;

    const In = 5;

    const NotIn = 6;


    public static function toString(int $type)
    {
        switch ($type){
            case 1:
                return "=";
            case 2:
                return "LIKE";
            case 3:
                return "BETWEEN";
            case 4:
                return "BETWEEN";
            case 5:
                return "IN";
            case 6:
                return "NOT IN";
        }
    }

}