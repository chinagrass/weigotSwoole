<?php

namespace Weigot\Swoole;

use App\Http\Service\UserService;
use App\Models\UserModel;
use Illuminate\Support\Facades\Redis;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Weigot\Swoole\Enum\ActionCodeEnum;
use Weigot\Swoole\Enum\ClientActionEnum;
use Weigot\Swoole\Enum\MsgTypeEnum;

class WebSocketServer
{
    protected $config;
    protected $server;
    protected $app;
    protected $heartBeatInternal;

    public function __construct($swooleConfig)
    {
        $this->config = $swooleConfig;
        $this->heartBeatInternal = $this->config["server"]['heart_beat_internal'];
        $this->server = new Server($this->config["server"]['host'], $this->config["server"]['port']);
    }

    /**
     * @return Server
     */
    public function getServer(): Server
    {
        return $this->server;
    }

    public function run()
    {
        unset($this->config["server"]['host'], $this->config["server"]['port'], $this->config["server"]['heart_beat_internal']);
        $ws = $this->getServer();
        $ws->set($this->config["server"]);
        // 监听连接进入事件
        $ws->on("connect", [$this, "onConnect"]);
        //监听WebSocket连接打开事件
        $ws->on("open", [$this, "onOpen"]);
        //监听WebSocket消息事件
        $ws->on("message", [$this, "onMessage"]);
        //监听WebSocket连接关闭事件
        $ws->on("close", [$this, "onClose"]);
        //监听WebSocket请求参数
        $ws->on("request", [$this, "onRequest"]);
        $ws->start();
    }

    /**
     * 连接事件
     * @param Server $ws
     * @param $fd
     */
    public function onConnect(Server $ws, $fd)
    {
        //在这里检测这个$fd，没问题再confirm
        $ws->confirm($fd);
    }

    /**
     * 连接打开事件
     * @param Server $ws
     * @param Request $request
     */
    public function onOpen(Server $ws, Request $request)
    {
        try {
            $get = $request->get;
            $post = $request->post;
            $header = $request->header;
            $sign = $header["sign"] ?? ($post["sign"] ?? ($get["sign"] ?? ""));
            $room = $header["room"] ?? ($post["room"] ?? ($get["room"] ?? ""));
            // @do 查询用户
            $user = UserServer::userInfoBySign($sign);
            // @do 查询用户或者房间不存在就断开链接
            if (empty($user) || empty($room)) {
                $ws->disconnect($request->fd, SWOOLE_WEBSOCKET_CLOSE_NORMAL, '认证失败');
            } else {
                $user["groupId"] = $room;
                UserServer::setUserInfoById($user);
                $ws->bind($request->fd, $user["id"]);
                // 记录fd和用户标志的对应关系(利用有序集合特点，fd做分值，userId做value)
                UserServer::roomAddUser($room, $user["id"], $request->fd);
                // 绑定用户id和fd
                UserServer::bindUserIdFd($user["id"], $request->fd);
                $roomUserList = [];
                // @do 获得列表中所有成员
                $users = UserServer::getUsersByRoom($room);
                foreach ($users as $userId => $fd) {
                    $fd = (int)$fd;
                    $userInfo = UserServer::userInfoById($userId);
                    if (!$userInfo) {
                        continue;
                    }
                    unset($userInfo["id"]);
                    $roomUserList[$fd] = $userInfo;
                    if ($fd == $request->fd) {
                        continue;
                    }
                    // 向用户通知新用户上线
                    $online = [
                        "type" => MsgTypeEnum::STATE,
                        "username" => $user["username"],
                        "fd" => $request->fd,
                        "content" => $user
                    ];
                    unset($online["content"]["id"]);
                    $msg = MessageServer::formatData(ActionCodeEnum::NEW_USER_ONLINE, $online);
                    $ws->push($fd, $msg);
                }
                // 欢迎语
                $welcome = [
                    "type" => MsgTypeEnum::TEXT,
                    "username" => $user["username"],
                    "fd" => $request->fd,
                    "content" => [
                        $this->config["msg"]["welcome"]
                    ]
                ];
                $result = MessageServer::formatData(ActionCodeEnum::ADMIN, $welcome);
                $ws->push($request->fd, $result);
                //@do 查询所有用户并发送用户列表
                $userQuery = [
                    "type" => MsgTypeEnum::STATE,
                    "username" => "",
                    "fd" => "",
                    "content" => $roomUserList
                ];
                $list = MessageServer::formatData(ActionCodeEnum::USER_LIST, $userQuery);
                $ws->push($request->fd, $list);
            }
        } catch (\Exception $e) {
            echo $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage() . "\n";
        }

    }

    /**
     * 消息事件
     * @param Server $ws
     * @param Frame $frame
     */
    public function onMessage(Server $ws, Frame $frame)
    {
        try {
            $json = $frame->data;
            $data = json_decode($json, true);
            if (empty($data["action"])) {
                $ws->push($frame->fd, "action is error");
            };
            $fd = $frame->fd;
            $connections = $ws->connections;
            switch ($data["action"]) {
                case ClientActionEnum::MESSAGE:
                    $msgData = [
                        "type" => MsgTypeEnum::TEXT,
                        "username" => "",
                        "fd" => $fd, // 发送者fd
                        "content" => [
                            $data["params"]["content"]
                        ]
                    ];
                    $message = MessageServer::formatData(ActionCodeEnum::SEND_MSG, $msgData);
                    foreach ($connections as $connectionFd) {
                        // 向所有用户推送消息
                        $ws->push($connectionFd, $message);
                    }
                    break;
                case ClientActionEnum::INFO:
                    // @do 查询userID
                    $userIds = UserServer::getBindUserIds($fd);
                    //@do 查询用户信息
                    foreach ($userIds as $userId) {
                        $userInfo = UserServer::userInfoById($userId);
                        if (!empty($userInfo)) {
                            break;
                        }
                    }
                    if (!empty($userInfo)) {
                        unset($userInfo["id"]);
                        //@do 发送消息
                        $msgData = [
                            "type" => MsgTypeEnum::STATE,
                            "username" => $userInfo["username"],
                            "fd" => $fd, // 发送者fd
                            "content" => $userInfo
                        ];
                    }
                    $message = MessageServer::formatData(ActionCodeEnum::USER_INFO, $msgData);
                    $ws->push($fd, $message);
                    break;
                case ClientActionEnum::ONLINE_LIST:// 在线用户列表
                    // @do 查询userID
                    $userIds = UserServer::getBindUserIds($fd);
                    //@do 查询用户信息
                    foreach ($userIds as $userId) {
                        $userInfo = UserServer::userInfoById($userId);
                        if (!empty($userInfo)) {
                            break;
                        }
                    }
                    if (!empty($userInfo)) {
                        $room = $userInfo["groupId"];
                        // @do 获得列表中所有成员
                        $users = UserServer::getUsersByRoom($room);
                        $roomUserList = [];
                        foreach ($users as $userId => $ufd) {
                            $ufd = (int)$ufd;
                            $userInfo = UserServer::userInfoById($userId);
                            if (!$userInfo) {
                                continue;
                            }
                            unset($userInfo["id"]);
                            $roomUserList[$ufd] = $userInfo;
                        }
                        //@do 查询所有用户并发送用户列表
                        $userQuery = [
                            "type" => MsgTypeEnum::STATE,
                            "username" => "",
                            "fd" => "",
                            "content" => $roomUserList
                        ];
                        $list = MessageServer::formatData(ActionCodeEnum::USER_LIST, $userQuery);
                        $ws->push($frame->fd, $list);
                    }
                    break;
            }
        } catch (\Exception $e) {
            echo $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage() . "\n";
        }

    }

    /**
     * 关闭连接事件
     * @param Server $ws
     * @param $fd
     */
    public function onClose(Server $ws, $fd)
    {
        try {
            // 获得绑定的用户id
            $users = UserServer::getBindUserIds($fd);
            foreach ($users as $userId) {
                $userInfo = UserServer::userInfoById($userId);
                if (!empty($userInfo["groupId"])) {
                    //@do 删除房间中的这个用户
                    UserServer::removeUserFromRoom($userInfo, $userId);
                }
                // 发送用户离开的消息
                $outMsg = [
                    "type" => MsgTypeEnum::STATE,
                    "username" => $userInfo["username"],
                    "fd" => $fd,
                    "content" => []
                ];
                $message = MessageServer::formatData(ActionCodeEnum::USER_OUTLINE, $outMsg);
                //@do 查询数据,离开房间
                foreach ($ws->connections as $connectionFd) {
                    $ws->push($connectionFd, $message);
                }
            }
        } catch (\Exception $e) {
            echo $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage() . "\n";
        }

    }

    /**
     * 请求消息事件
     * @param $request
     * @param $response
     */
    public function onRequest($request, $response)
    {

    }
}