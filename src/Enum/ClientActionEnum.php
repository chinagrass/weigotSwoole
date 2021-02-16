<?php

namespace Weigot\Swoole\Enum;


class ClientActionEnum
{
    const MESSAGE = "message";          // 发送消息
    const INFO = "info";                // 用户获取详情
    const ONLINE_LIST = "onlineList";   // 在线用户列表
    const PERSON_MESSAGE = "personMessage"; // 向某人发送消息
}