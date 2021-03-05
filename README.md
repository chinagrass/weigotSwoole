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
2.操作命令
```$xslt
$ php artisan weigot-ws start | restart | reload | stop | status   // 运行 | 重启 | 重新加载 | 停止 | 状态
```
3.使用supervisor守护进程
```$xslt
[program:${program-name}]        # 项目名称
directory= ${project-directory}  # 项目路径
process_name=%(program_name)s_%(process_num)02d
command=php artisan weigot-sw start  # 运行命令
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=4
redirect_stderr=false
stdout_logfile=${log-file}  # 日志记录文件
```
4.nginx配置
```$xslt
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

upstream websocket {
    #ip_hash;
    #转发到服务器上相应的ws端口
    server 0.0.0.0:9601;
}

server {
    #listen后面的端口号改为你自己需要的端口号
    listen 80;
    #server_name改为你自己的外网ip。server_name默认为localhost即127.0.0.1
    server_name sw.vm.com;

    root /var/www/html/works/lumenChat/public;
    index index.php index.html index.htm;

    location / {
         try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
       fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
       fastcgi_split_path_info ^(.+\.php)(/.+)$;
       fastcgi_index /index.php;
       fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
       include fastcgi_params;
    }
    location /ws {
        #转发到http://websocket
        proxy_pass http://websocket;
        proxy_read_timeout 300s;
        proxy_send_timeout 300s;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        #升级http1.1到 websocket协议
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection  $connection_upgrade;
    }
}
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
        "content":{     // 内容
            "message":"",
            "userList":[
                {
                    "fd":"",
                    "username":"",
                    ...
                },
                ...
            ],
            "userInfo":{
                "fd":"",
                "username":"",
                ...
            }
        }
    },
    "endTime":""
}
```
2.服务端action枚举

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

4.客户端action枚举

|**名称**|**编码**|
|----|----|
|发送消息|message|
|获取自己详情|info|
|请求在线用户列表|onlineList|





