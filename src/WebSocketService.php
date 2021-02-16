<?php

namespace Weigot\Swoole;

use Illuminate\Support\Facades\Redis;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Weigot\Swoole\Enum\ClientActionEnum;

class WebSocketService
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
        $serverConfig = $this->config["server"];
        unset($serverConfig['host'], $serverConfig['port'], $serverConfig['heart_beat_internal']);
        $ws = $this->getServer();
        $ws->set($serverConfig);
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
            $user = UserService::userInfoBySign($sign);
            // @do 查询用户或者房间不存在就断开链接
            if (empty($user) || empty($room)) {
                $ws->disconnect($request->fd, SWOOLE_WEBSOCKET_CLOSE_NORMAL, '认证失败');
            } else {
                $user["fd"] = $request->fd;
                $user["groupId"] = $room;
                UserService::setUserInfoById($user);
                $ws->bind($request->fd, $user["id"]);
                // 记录fd和用户标志的对应关系(利用有序集合特点，fd做分值，userId做value)
                UserService::roomAddUser($room, $user["id"], $request->fd);
                // 绑定用户id和fd
                UserService::bindUserIdFd($user["id"], $request->fd);
                $roomUserList = [];
                // @do 获得列表中所有成员
                $users = UserService::getUsersByRoom($room);
                foreach ($users as $userId => $fd) {
                    $fd = (int)$fd;
                    $userInfo = UserService::userInfoById($userId);
                    if (!$userInfo) {
                        continue;
                    }
                    unset($userInfo["id"]);
                    $userInfo["fd"] = $fd;
                    $roomUserList[$fd] = $userInfo;
                    if ($fd == $request->fd) {
                        continue;
                    }
                    // 向用户通知新用户上线
                    MessageService::pushNewUserOnline($fd, $user, $ws);
                }
                // 欢迎语
                MessageService::pushWelcome($request->fd, $this->config["msg"]["welcome"], $ws);
                //@do 查询所有用户并发送用户列表
                MessageService::pushRoomUserList($request->fd, $roomUserList, $ws);
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
            $userInfo = [];
            $fd = $frame->fd;
            $json = $frame->data;
            if ($json == 'PING') {
                MessageService::pushPong($fd, $ws);
                return true;
            }
            $data = json_decode($json, true);
            if (empty($data["action"])) {
                $ws->push($frame->fd, "action is error");
                return true;
            };
            // @do 查询userID
            $userIds = UserService::getBindUserIds($fd);
            //@do 查询用户信息
            foreach ($userIds as $userId) {
                $userInfo = UserService::userInfoById($userId);
                if (!empty($userInfo)) {
                    $userInfo['fd'] = $fd;
                    break;
                }
            }
            $connections = $ws->connections;
            switch ($data["action"]) {
                case ClientActionEnum::MESSAGE:
                    foreach ($connections as $connectionFd) {
                        // 向所有用户推送消息
                        MessageService::pushMsg($connectionFd, $data["params"]["content"], $userInfo, $ws);
                    }
                    break;
                case ClientActionEnum::INFO:
                    MessageService::pushUserInfo($fd, $userInfo, $ws);
                    break;
                case ClientActionEnum::ONLINE_LIST:// 在线用户列表
                    // @do 查询userID
                    $userIds = UserService::getBindUserIds($fd);
                    //@do 查询用户信息
                    foreach ($userIds as $userId) {
                        $userInfo = UserService::userInfoById($userId);
                        if (!empty($userInfo)) {
                            break;
                        }
                    }
                    if (!empty($userInfo)) {
                        $room = $userInfo["groupId"];
                        // @do 获得列表中所有成员
                        $users = UserService::getUsersByRoom($room);
                        $roomUserList = [];
                        foreach ($users as $userId => $ufd) {
                            $ufd = (int)$ufd;
                            $userInfo = UserService::userInfoById($userId);
                            if (!$userInfo) {
                                continue;
                            }
                            unset($userInfo["id"]);
                            $userInfo["fd"] = $ufd;
                            $roomUserList[$ufd] = $userInfo;
                        }
                        MessageService::pushRoomUserList($frame->fd, $roomUserList, $ws);
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
            $users = UserService::getBindUserIds($fd);
            foreach ($users as $userId) {
                $userInfo = UserService::userInfoById($userId);
                if (!empty($userInfo["groupId"])) {
                    //@do 删除房间中的这个用户
                    UserService::removeUserFromRoom($userInfo, $userId);
                }
                $userInfo["fd"] = $fd;
                //@do 查询数据,离开房间
                foreach ($ws->connections as $connectionFd) {
                    // 发送用户离开的消息
                    MessageService::pushUserOutline($connectionFd, $userInfo, $ws);
                }
            }
        } catch (\Exception $e) {
            echo $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage() . "\n";
        }

    }
}