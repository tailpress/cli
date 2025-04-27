<?php

namespace TailPress\Cli;

use TailPress\Framework\ServiceProvider;

class CliServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->commands()->add(\TailPress\Cli\Commands\Inspire::class);
        $this->app->commands()->add(\TailPress\Cli\Commands\Release::class);
    }
}
