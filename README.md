## 基于ThinkPHP的ORM搜索器


### 为什么会有它？
经常被一些搜索弄的代码可读性直线下降，例如公司之前维护的老系统代码的搜索是这样的

```php
$username = $request->get("username");
if (!isset($keyword)){
    $where[] = ['u.username', 'like', "%{$keyword}%"];
}

$create_time = $request->get("create_time");
if (!isset($create_time)){
    $times = explode($create_time)
    $where[] = ['u.create_time', 'between', $times[0], $time[1]];
}

...此处省略其它代码
```

抛开之前同事写的代码严谨性问题，光阅读性就已经开始让人头疼了

当然ThinkPHP也实现了搜索器查询,但当联表JOIN查询时，把搜索器方法写在不属于自己的模型类中时，属实是让我这个代码洁癖难以接受

于是趁业务时间封装出了一套搜索器

### 示例

```php
// 假设这是前端请求时传入的查询参数
$requestParams = [
    "username" => "1111",
    "create_time" => "2024-01-01,2024-03-01"
];

$searchWhere = ThinkSearch::getInstance($requestParams)
    ->addMapping(columnName:"username", selectType: SearchType::Like, desc: "用户名")
    ->addMapping(columnName:"create_time", selectType: SearchType::BetweenTime, desc: "创建时间")
    ->build();

// 您得到了一个可用于ORM查询的数组
Db::table("you_table")->where($searchWhere);
```

### 输出SQL(复杂实例)
```php
$requestParams = [
            "type" => "1",
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
// 结果输出
WHERE a.email = '1' AND ( u.username LIKE '13277777777'  OR  u.phone LIKE '13277777777'  OR  u.account LIKE '13277777777' ) AND u.create_time BETWEEN 1704067200 AND 1709337599 AND u.level IN (1,2,3)

```


### 时间查询
当使用SearchType::BetweenTime时，支持以下格式<br />
```php
[
    // Date 字符串格式
    "create_time" => "2024-01-01,2024,03-01",
    // DateTime 字符串格式
    "create_time" => "2024-01-01 00:00:00,2024-03-01 23:59:59",
    // Date Array
    "create_time" => [
        '2024-01-01',
        '2024,03-01'
    ],
    // DateTime Array
    "create_time" => [
        '2024-01-01 00:00:00',
        '2024-03-01 23:59:59'
    ],
];
```

### 自定义字段值处理
```php
$requestParams = [
    "level" => "1",
];

$searchWhere = ThinkSearch::getInstance($requestParams)
    ->addMapping(
        columnName: "level",
        aliasName: "c.level",
        valueFormater: fn ($val) => match((int)$val){
            1 => [1,2,3],
            2 => [4,5,6]
        },
        selectType: SearchType::In
    )
    ->build();
var_dump($searchWhere);

// 结果输出
array(1) {
  [0]=>
  array(3) {
    [0]=>
    string(7) "c.level"
    [1]=>
    string(2) "in"
    [2]=>
    array(3) {
      [0]=>
      int(1)
      [1]=>
      int(2)
      [2]=>
      int(3)
    }
  }
}
```

### 自定义字段名称处理
```php
$requestParams = [
    "type" => "1",
];

$searchWhere = ThinkSearch::getInstance($requestParams)
    ->addMapping(
        columnName: "type",
        nameFormatter: fn ($val) => match((int)$val){
            1 => "a.email",
            2 => "a.phone"
        },
    )
    ->build();
var_dump($searchWhere);

// 结果输出
Array
(
    [a.email] => 1
)
```

### 注意事项
1. 搜索器默认会过滤空字符串及空数组，若您觉得不满意可指定filter自定义规则函数
2. 若使用字符串格式，则保证分隔符需为英文逗号(,)

### AddMapping参数描述

|  参数   | 描述  |
|  ----  | ----  |
|  columnName   | 需要处理的字段名称(通常用于请求参数中的名称)  |
|  aliasName|别名,如使用联表查询 则可设置该属性|
|  selectType|字段匹配模式｜
|valueFormater|自定义字段值处理函数(return: mixed)|
|filter|自定义规则函数(return: bool)
|nameFormatter|自定义字段名称函数(return mixed)|
|desc|参数描述信息|

### 匹配模式
指定addMapping方法中的selectType参数将匹配不同的搜索器模式
|  参数   | 描述  |sql参考|
|  ----  | ----  | ---- |
|  SearchType::Equals   | 默认值，比较模式  |where type = 1|
|SearchType::Like|模糊搜索模式|where username like "%张三%"|
|SearchType::Between|区间搜索模式|where age BETWEEN 18 AND 30|
|SearchType::BetweenTime|时间搜索模式(在区间搜索模式基础上加入补充时间后位数及时间戳格式化)||
|SearchType::In|in查询|where id in (1,2,3)｜
|SearchType::In|not in查询|where id not in (1,2,3)｜