<?php

namespace Keggermont\LaravelPipedrive\Commands;

use Illuminate\Console\Command;

class LaravelPipedriveCommand extends Command
{
    public $signature = 'laravel-pipedrive';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
