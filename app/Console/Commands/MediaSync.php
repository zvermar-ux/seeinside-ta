<?php

namespace App\Console\Commands;

use App\Services\Media\MediaLibrary;
use Illuminate\Console\Command;

class MediaSync extends Command
{
    protected $signature = 'media:sync';

    protected $description = 'Add every image already used in the app to the media library';

    public function handle(): int
    {
        $this->info(MediaLibrary::syncExisting().' images added to the library');

        return self::SUCCESS;
    }
}
