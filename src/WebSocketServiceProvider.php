<?php
namespace Weigot\Swoole;

use Illuminate\Support\ServiceProvider;

class WebSocketServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([
            Command::class,
        ]);
        $this->app->singleton('swoole.http', function ($app) {
            $this->mergeConfigFrom(
                __DIR__ . '/../config/wgSwoole.php', 'wgSwoole'
            );
            $swooleConfig = $app['config']['wgSwoole'];
            return new WebSocketService($swooleConfig);
        });
    }
}