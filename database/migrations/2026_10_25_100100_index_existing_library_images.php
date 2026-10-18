<?php

use App\Services\Media\MediaLibrary;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // fill the new media library with the images already used in the app
    public function up(): void
    {
        try {
            MediaLibrary::syncExisting();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function down(): void {}
};
