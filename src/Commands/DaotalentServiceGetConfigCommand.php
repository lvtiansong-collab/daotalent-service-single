<?php

namespace Wiltechsteam\DaotalentServiceSingle\Commands;

use Illuminate\Console\Command;

class DaotalentServiceGetConfigCommand extends Command
{
    protected $signature = 'daotalent:config';

    protected $description = 'Publish Daotalent config file';

    public function handle()
    {
        $configPath = config_path('daotalent.php');
        copy(__DIR__ . '/../config/daotalent.php', $configPath);
        $this->info('Config file published: config/daotalent.php');
    }
}
