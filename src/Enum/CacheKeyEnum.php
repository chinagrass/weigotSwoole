<?php

namespace Weigot\Swoole\Enum;


class CacheKeyEnum
{
    // fd绑定uid的key
    const BIND_UID_KEY = 'weigot:swoole:bind:uid:fd';
    // 房间中用户和fd绑定uid的key
    const ROOM_UID_KEY = 'weigot:swoole:chat:room:uid:';
    // 房间key,存储进入房间的用户的uid
    const ROOM_KEY = 'weigot:swoole:chat:room:';
    // 签名key
    const SIGN_KEY = 'weigot:swoole:sign:';
    // 用户详情
    const USER_INFO_KEY = 'weigot:swoole:user:info:';
    // 限制连接数量
    const LIMIT_CONNECTIONS = 'weigot:swoole:limit:connections:';
}