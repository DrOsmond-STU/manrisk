<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Improvement terhubung ke sumbernya (kontrol/KRI/insiden/review) dan ke risiko terkait. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('improvements', function (Blueprint $t) {
            $t->string('subject_type', 40)->nullable()->after('source_ref');
            $t->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $t->foreignId('risk_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();
            $t->index(['subject_type', 'subject_id']);
        });
        // Isi relasi untuk data lama berdasarkan kode sumber
        $map = ['control_failure' => ['controls', 'control'], 'kri_breach' => ['kris', 'kri'], 'incident' => ['incidents', 'incident']];
        foreach (DB::table('improvements')->whereNull('subject_id')->whereNotNull('source_ref')->get(['id', 'organization_id', 'source_type', 'source_ref']) as $imp) {
            if (!isset($map[$imp->source_type])) {
                continue;
            }
            [$table, $type] = $map[$imp->source_type];
            $row = DB::table($table)->where('organization_id', $imp->organization_id)->where('code', $imp->source_ref)->first();
            if (!$row) {
                continue;
            }
            $risk = match ($type) {
                'kri', 'incident' => $row->risk_id,
                'control' => DB::table('control_risk')->where('control_id', $row->id)->value('risk_id'),
            };
            DB::table('improvements')->where('id', $imp->id)->update(['subject_type' => $type, 'subject_id' => $row->id, 'risk_id' => $risk]);
        }
    }

    public function down(): void
    {
        Schema::table('improvements', function (Blueprint $t) {
            $t->dropConstrainedForeignId('risk_id');
            $t->dropIndex(['subject_type', 'subject_id']);
            $t->dropColumn(['subject_type', 'subject_id']);
        });
    }
};
