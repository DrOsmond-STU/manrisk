<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Realisasi biaya perlakuan risiko, dilaporkan dalam laporan manajemen risiko triwulanan BUMN. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_plans', function (Blueprint $table) {
            $table->decimal('actual_cost', 18, 2)->nullable()->after('budget');
        });
    }

    public function down(): void
    {
        Schema::table('action_plans', fn (Blueprint $t) => $t->dropColumn('actual_cost'));
    }
};
