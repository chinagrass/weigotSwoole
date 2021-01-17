<?php

namespace WeigotSwoole;

use Swoole\WebSocket\Server;

class WebSocketServer
{
    protected $config;
    protected $server;
    protected $app;
    protected $heartBeatInternal;

    public function __construct($swooleConfig)
    {
        $this->config = $swooleConfig;
        $this->heartBeatInternal = $this->config['heart_beat_internal'];
        $this->server = new Server($this->config['host'], $this->config['port']);
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
        $ws = $this->getServer();
        // set config
        $ws->set($this->config);
        //监听WebSocket连接打开事件
        $this->onOpen();
        //监听WebSocket消息事件
        $this->onMessage();
        //监听WebSocket连接关闭事件
        $this->onClose();

        $this->onRequest();
        // start swoole http server
        $ws->start();
    }

    public function onOpen()
    {
        $ws = $this->getServer();
        $ws->on('open', function ($ws, $request) {
            var_dump($request->fd, $request->server);
            $ws->push($request->fd, "hello, welcome\n");
        });
    }

    public function onMessage()
    {
        $ws = $this->getServer();
        $ws->on('message', function ($server, $request) {
            $resut = json_decode($request->data);
            $t_id = $resut[0];
            $msg = $resut[1];
            if (is_numeric($t_id)) {  //单发
                $num = 0;
                foreach ($server->connections as $conn) {
                    if ($conn == $t_id) {  //防止要发送的对方已经不在线了
                        $server->push($t_id, $msg);  //主动发送给$t_d
                    }
                    $num++;
                }
                echo '当前在线人数' . $num;

            } else {  //群发
                foreach ($server->connections as $conn) {
                    $server->push($conn, $msg);
                }
            }
        });
    }

    public function onClose()
    {
        $ws = $this->getServer();
        $ws->on('close', function ($ws, $fd) {
            echo "client-{$fd} is closed\n";
        });
    }

    public function onRequest()
    {
        $ws = $this->getServer();
        $ws->on('request', function ($request, $response) {
            // 接收http请求从get获取message参数的值，给用户推送
            // $this->server->connections 遍历所有websocket连接用户的fd，给所有用户推送
            foreach ($this->server->connections as $fd) {
                // 需要先判断是否是正确的websocket连接，否则有可能会push失败
                if ($this->server->isEstablished($fd)) {
                    $this->server->push($fd, $request->get['message']);
                }
            }
        });
    }
}