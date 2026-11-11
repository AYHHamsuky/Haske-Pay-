<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone', 11)->unique(); // local format 0XXXXXXXXXX
            $table->string('email')->nullable();
            $table->enum('role', ['customer', 'agent', 'admin'])->default('customer');
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->string('locale', 2)->default('en');
            $table->string('pin_hash')->nullable();
            $table->unsignedTinyInteger('pin_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 11)->index();
            $table->string('code_hash');
            $table->string('purpose', 20)->default('login');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
