<?php

namespace Weigot\Swoole\Enum;

class ActionCodeEnum
{
    /** @var int 服务端操作 */
    const SERVER = 100;
    /** @var int 管理员操作 */
    const ADMIN = 101;
    /** @var int 其他用户操作 */
    const OTHER_USER = 102;
    /** @var int 发送消息 */
    const SEND_MSG = 103;
    /** @var int 用户详情 */
    const USER_INFO = 104;
    /** @var int 用户列表 */
    const USER_LIST = 201;
    /** @var int 新用户上线 */
    const NEW_USER_ONLINE = 202;
    /** @var int 用户下线 */
    const USER_OUTLINE = 203;
}