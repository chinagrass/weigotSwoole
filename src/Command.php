<?php

namespace Weigot\Swoole;

use Illuminate\Console\Command as IlluminateCommand;

class Command extends IlluminateCommand
{
    protected $signature = 'weigot-ws {action : how to handle the server}';

    protected $description = 'Handle swoole http server with start | restart | reload | stop | status';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $action = $this->argument('action');
        switch ($action) {
            case 'start':
                $this->start();
                break;
            case 'restart':
                $this->restart();
                break;
            case 'reload':
                $this->reload();
                break;
            case 'stop':
                $this->stop();
                break;
            case 'status':
                $this->status();
                break;
            default:
                $this->error('Please type correct action . start | restart | stop | reload | status');
        }
    }

    protected function start()
    {
        if ($this->getPid()) {
            $this->error('swoole server is already running');
            exit(1);
        }

        $this->info('starting swoole websocket server...');
        app()->make('swoole.http')->run();
    }

    protected function restart()
    {
        $this->info('stopping swoole server...');
        $pid = $this->sendSignal(SIGTERM);
        $time = 0;
        while (posix_getpgid($pid)) {
            usleep(100000);
            $time++;
            if ($time > 50) {
                $this->error('timeout...');
                exit(1);
            }
        }
        $this->info('done');
        $this->start();
    }

    protected function reload()
    {
        $this->info('reloading...');
        $this->sendSignal(SIGUSR1);
        $this->info('done');
    }

    protected function stop()
    {
        $this->info('immediately stopping...');
        $this->sendSignal(SIGTERM);
        $this->info('done');
    }

    protected function status()
    {
        $pid = $this->getPid();
        if ($pid) {
            $this->info('swoole server is running. master pid : ' . $pid);
        } else {
            $this->error('swoole server is not running!');
        }
    }

    protected function sendSignal($sig)
    {
        $pid = $this->getPid();
        if ($pid) {
            posix_kill($pid, $sig);
        } else {
            $this->error('swoole is not running!');
            exit(1);
        }
        return $pid;
    }

    protected function getPid()
    {
        $pid_file = config('wgSwoole.server.pid_file');
        if (!$pid_file) {
            $config = require_once __DIR__ . '/../config/wgSwoole.php';
            $pid_file = $config["server"]["pid_file"];
        }
        if (file_exists($pid_file)) {
            $pid = intval(file_get_contents($pid_file));
            if (posix_getpgid($pid)) {
                return $pid;
            } else {
                unlink($pid_file);
            }
        }
        return false;
    }
}