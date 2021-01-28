<?php
/**
 * Created By PhpStorm
 * User: 曹伟
 * Date: 2021/1/28
 */

namespace Weigot\Swoole;


use Weigot\Swoole\Enum\ActionCodeEnum;
use Weigot\Swoole\Enum\MsgTypeEnum;

class MessageServer
{
    public static function formatData($action, $data = [])
    {
        $message = [
            "action" => $action,
            "data" => $data,
            "sendTime" => date("Y-m-d H:i:s", time()),
        ];
        return json_encode($message, JSON_UNESCAPED_UNICODE);
    }
}