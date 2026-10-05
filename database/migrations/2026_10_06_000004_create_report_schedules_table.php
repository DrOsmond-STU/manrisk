<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('format', 10);
            $table->string('frequency', 10)->default('monthly'); // monthly | weekly
            $table->unsignedTinyInteger('day')->default(1);     // tanggal (bulanan) atau 1–7 hari (mingguan)
            $table->json('recipients');
            $table->json('params')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
