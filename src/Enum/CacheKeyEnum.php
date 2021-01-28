<?php

namespace Weigot\Swoole\Enum;


class CacheKeyEnum
{
    const BIND_UID_KEY = 'weigot:swoole:bind:uid:fd';// fd绑定uid的key
    const ROOM_UID_KEY = 'weigot:swoole:chat:room:uid:';// 房间中用户和fd绑定uid的key
    const ROOM_KEY = 'weigot:swoole:chat:room:';// 房间key,存储进入房间的用户的uid
    const SIGN_KEY = 'weigot:swoole:sign:'; // 签名key
    const USER_INFO_KEY = 'weigot:swoole:user:info:'; // 用户详情
}