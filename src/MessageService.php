<?php
/**
 * Created By PhpStorm
 * User: 曹伟
 * Date: 2021/1/28
 */

namespace Weigot\Swoole;


use Swoole\WebSocket\Server;
use Weigot\Swoole\Enum\ActionCodeEnum;
use Weigot\Swoole\Enum\MsgTypeEnum;

class MessageService
{
    /**
     * 格式化数据
     * @param $action
     * @param array $data
     * @return false|string
     */
    public static function formatData($action, $data = [])
    {
        $message = [
            "action" => $action,
            "data" => $data,
            "sendTime" => date("Y-m-d H:i:s", time()),
        ];
        return json_encode($message, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 发送欢迎语
     * @param $fd
     * @param $msg
     * @param Server $ws
     * @param string $msgType
     */
    public static function pushWelcome($fd, $msg, Server $ws, $msgType = MsgTypeEnum::TEXT)
    {
        $msg = htmlspecialchars($msg);
        $welcome = [
            "type" => $msgType,
            "content" => ["message" => $msg]
        ];
        $result = self::formatData(ActionCodeEnum::ADMIN, $welcome);
        $ws->push($fd, $result);
    }

    /**
     * 发送房间中的用户列表
     * @param $fd
     * @param $roomUserList
     * @param Server $ws
     */
    public static function pushRoomUserList($fd, $roomUserList, Server $ws)
    {
        $userQuery = [
            "type" => MsgTypeEnum::STATE,
            "content" => ["userList" => $roomUserList],
        ];
        $list = self::formatData(ActionCodeEnum::USER_LIST, $userQuery);
        $ws->push($fd, $list);
    }

    /**
     * 通知新用户上线
     * @param $fd
     * @param $user
     * @param Server $ws
     */
    public static function pushNewUserOnline($fd, $user, Server $ws)
    {
        unset($user["id"]);
        $online = [
            "type" => MsgTypeEnum::STATE,
            "content" => ["userInfo" => $user]
        ];
        $msg = self::formatData(ActionCodeEnum::NEW_USER_ONLINE, $online);
        $ws->push($fd, $msg);
    }

    /**
     * 用户详情
     * @param $fd
     * @param $user
     * @param Server $ws
     */
    public static function pushUserInfo($fd, $user, Server $ws)
    {
        unset($user["id"]);
        $user["fd"] = $fd;
        $userInfo = [
            "type" => MsgTypeEnum::STATE,
            "content" => ["userInfo" => $user]
        ];
        $msg = self::formatData(ActionCodeEnum::USER_INFO, $userInfo);
        $ws->push($fd, $msg);
    }

    /**
     * 推送服务端消息
     * @param $fd
     * @param $msg
     * @param Server $ws
     * @param $msgType
     */
    public static function pushServiceMsg($fd, $msg, Server $ws, $msgType = MsgTypeEnum::TEXT)
    {
        $msg = htmlspecialchars($msg);
        $msg = [
            "type" => $msgType,
            "content" => [
                "message" => $msg
            ]
        ];
        $msg = self::formatData(ActionCodeEnum::SERVER, $msg);
        $ws->push($fd, $msg);
    }

    /**
     * 发送消息
     * @param $fd
     * @param $msg
     * @param $user
     * @param Server $ws
     * @param string $msgType
     */
    public static function pushMsg($fd, $msg, $user, Server $ws, $msgType = MsgTypeEnum::TEXT)
    {
        $msg = htmlspecialchars($msg);
        unset($user["id"]);
        $msg = [
            "type" => $msgType,
            "content" => [
                "userInfo" => $user,
                "message" => $msg
            ]
        ];
        $msg = self::formatData(ActionCodeEnum::SEND_MSG, $msg);
        $ws->push($fd, $msg);
    }

    /**
     * 用户下线
     * @param $fd
     * @param $user
     * @param Server $ws
     */
    public static function pushUserOutline($fd, $user, Server $ws)
    {
        unset($user["id"]);
        $msg = [
            "type" => MsgTypeEnum::TEXT,
            "content" => [
                "userInfo" => $user,
            ]
        ];
        $msg = self::formatData(ActionCodeEnum::USER_OUTLINE, $msg);
        $ws->push($fd, $msg);
    }

    /**
     * @param $fd
     * @param Server $ws
     */
    public static function pushPong($fd, Server $ws)
    {
        $ws->push($fd, 'PONG');
    }

}