<?php

namespace Wiltechsteam\DaotalentServiceSingle;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Wiltechsteam\DaotalentServiceSingle\Commands\DaotalentSingleCommand;
use Wiltechsteam\DaotalentServiceSingle\Commands\DaotalentServiceGetConfigCommand;
use Wiltechsteam\DaotalentServiceSingle\Commands\DaotalentServiceMakeCommand;

class DaotalentServiceSingleProvider extends ServiceProvider
{
    public function boot()
    {
        $this->eventBoot();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/daotalent.php', 'daotalent');

        $this->app->singleton('daotalent:work', DaotalentSingleCommand::class);
        $this->commands(['daotalent:work']);

        $this->app->singleton('daotalent:make', function () {
            return new DaotalentServiceMakeCommand();
        });
        $this->commands(['daotalent:make']);

        $this->app->singleton('daotalent:config', function () {
            return new DaotalentServiceGetConfigCommand();
        });
        $this->commands(['daotalent:config']);

        $this->bindEvents();
    }

    private function bindEvents()
    {
        foreach (config('daotalent.events') as $key => $className) {
            $this->app->bind($key, $className);
        }
    }

    /**
     * 批量绑定事件监听
     */
    public function eventBoot()
    {
        foreach (config('daotalent.listens') as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
