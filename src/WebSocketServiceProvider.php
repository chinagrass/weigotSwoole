<?php
namespace WeigotSwoole;

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
                __DIR__ . '/../config/swoole.php', 'swoole'
            );

            $swooleConfig = $app['config']['swoole'];
            return new WebSocketServer($swooleConfig);
        });
    }
}