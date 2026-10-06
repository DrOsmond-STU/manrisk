<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MFA berbasis kode email (OTP), kode pemulihan, perangkat tepercaya, dan pengaturan SMTP sistem. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('mfa_enabled')->default(false)->after('must_change_password');
            $t->timestamp('mfa_enabled_at')->nullable()->after('mfa_enabled');
            $t->text('mfa_recovery_codes')->nullable()->after('mfa_enabled_at');
        });

        Schema::create('mfa_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('purpose', 20);
            $t->string('code_hash', 64);
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['user_id', 'purpose', 'consumed_at']);
        });

        Schema::create('mfa_trusted_devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->string('user_agent', 255)->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('system_settings', function (Blueprint $t) {
            $t->string('key', 64)->primary();
            $t->longText('value')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('mfa_trusted_devices');
        Schema::dropIfExists('mfa_codes');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['mfa_enabled', 'mfa_enabled_at', 'mfa_recovery_codes']);
        });
    }
};
