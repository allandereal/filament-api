<?php

namespace Allandereal\FilamentApi\Commands;

use Illuminate\Console\Command;

class FilamentApiCommand extends Command
{
    public $signature = 'filament-api';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
