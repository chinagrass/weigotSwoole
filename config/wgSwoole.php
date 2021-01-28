<?php
return [
    "server" => [
        'host' => '0.0.0.0',
        'port' => 9601,
        'daemonize' => 0,
        'dispatch_mode' => 5,
        'worker_num' => 4,
        'max_request' => 5000,
        'log_file' => storage_path('logs/swoole.log'),
        'log_level' => 5,
        'pid_file' => storage_path('logs/swoole.pid'),
        'open_tcp_nodelay' => 1,
        'heart_beat_internal' => 300,
    ],
    "msg" => [
        "welcome" => "欢迎来到聊天室",
    ],
];