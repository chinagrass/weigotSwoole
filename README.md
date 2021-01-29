# 基于lumen框架的swoole扩展

公司使用lumen框架做项目较多，所以集合swoole来实现websocket供大家使用

## 使用文档

### 环境和依赖
1. "php": ">=7.0"

2. "ext-swoole": "*"

3. "laravel/lumen-framework": ">=5.5"

4. "illuminate/redis": "*"

### 使用方法

1.安装依赖包
```$xslt
$ composer require weigot/swoole
```
2.运行
```$xslt
$ php artisan weigot-sw start
```
3.使用supervisor守护进程
```$xslt

```
4.nginx配置
```$xslt

```
5.前端调用demo
```$xslt

```
### 数据结构
1. 消息结构
```$xslt
{
    "action":"",        // 行为code
    "data":{            // 消息
        "type":"",      // 消息类型
        "username":"",  // 用户
        "fd":"",        // fd
        "content":[]    // 内容
    }
}
```
2.action枚举

|**名称**|**编码**|
|----|----|
|服务端操作|100|
|管理员操作|101|
|其他用户操作|102|
|用户详情|200|
|用户列表|201|
|新用户上线|202|
|用户下线|203|
|发送消息|300|

3.信息类型

|**名称**|**编码**|
|----|----|
|文本消息|text|
|图片消息|img|
|状态信息|state|





