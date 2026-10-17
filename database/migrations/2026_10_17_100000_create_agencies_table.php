<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('MK');
            $table->string('tax_number')->nullable();
            $table->string('license_number')->nullable();
            $table->string('logo')->nullable();
            $table->string('default_currency', 3)->default('EUR');
            // SMTP settings (password is stored encrypted)
            $table->string('mail_from_address')->nullable();
            $table->string('mail_host')->nullable();
            $table->unsignedSmallInteger('mail_port')->nullable();
            $table->string('mail_encryption', 10)->nullable();
            $table->string('mail_username')->nullable();
            $table->text('mail_password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('locale', 5)->default('mk')->after('phone');
            $table->boolean('is_active')->default(true)->after('locale');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_id');
            $table->dropColumn(['phone', 'locale', 'is_active', 'last_login_at']);
        });

        Schema::dropIfExists('agencies');
    }
};
