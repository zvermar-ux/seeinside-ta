<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->nullable()->constrained()->cascadeOnDelete();   // NULL = shared (super admin)
            $t->string('path')->unique();                 // on the "public" disk
            $t->string('name', 190);
            $t->string('alt', 190)->nullable();
            $t->string('folder', 60)->nullable()->index();
            $t->string('mime', 60)->nullable();
            $t->unsignedInteger('size')->nullable();
            $t->unsignedSmallInteger('width')->nullable();
            $t->unsignedSmallInteger('height')->nullable();
            $t->string('source', 20)->default('upload');  // upload | scan | ai
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_files');
    }
};
