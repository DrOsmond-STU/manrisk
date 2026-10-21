<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dashboard AI Analitik yang disimpan pengguna: hanya spesifikasi widget, data selalu dihitung ulang sesuai hak akses penampil
        Schema::create('ai_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('prompt')->nullable();
            $table->json('widgets');
            $table->boolean('shared')->default(false);
            $table->timestamps();
            $table->index(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_dashboards');
    }
};
