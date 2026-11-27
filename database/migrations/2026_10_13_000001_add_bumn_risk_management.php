<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyesuaian dengan Permen BUMN PER-2/MBU/03/2023 dan petunjuk teknisnya:
 * skema level 5 tingkat, taksonomi bertingkat + kode Taksonomi Risiko Portofolio BUMN, risk limit,
 * kuantifikasi eksposur (Rp), target residual per triwulan, Kegiatan Korporasi Signifikan,
 * dan penilaian Risk Maturity Index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('criteria_versions', function (Blueprint $table) {
            $table->string('scheme', 20)->default('standard')->after('version'); // standard (4 level) | bumn (5 level)
        });

        Schema::table('risk_categories', function (Blueprint $table) {
            $table->string('bumn_code', 40)->nullable()->after('name_en');       // pemetaan ke Taksonomi Risiko Portofolio BUMN
            $table->decimal('risk_limit', 18, 2)->nullable()->after('tolerance'); // batas eksposur residual (Rp)
        });

        Schema::table('scopes', function (Blueprint $table) {
            $table->string('kind', 20)->default('regular')->after('name');        // regular | kks
            $table->string('kks_type', 80)->nullable()->after('kind');
            $table->decimal('transaction_value', 18, 2)->nullable()->after('kks_type');
            $table->string('decision_organ', 40)->nullable()->after('transaction_value'); // direksi | dekom | rups | menteri
            $table->date('decision_date')->nullable()->after('decision_organ');
            $table->text('conclusion')->nullable()->after('decision_date');
        });

        Schema::table('risks', function (Blueprint $table) {
            $table->foreignId('scope_id')->nullable()->after('process_id')->constrained('scopes')->nullOnDelete();
            $table->decimal('inherent_exposure', 18, 2)->nullable()->after('inherent_score');
            $table->decimal('residual_exposure', 18, 2)->nullable()->after('residual_score');
            $table->decimal('target_exposure', 18, 2)->nullable()->after('target_score');
        });

        Schema::table('risk_snapshots', function (Blueprint $table) {
            $table->decimal('residual_exposure', 18, 2)->nullable()->after('residual_score');
        });

        Schema::create('risk_quarter_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter'); // 1-4
            $table->unsignedTinyInteger('target_l');
            $table->unsignedTinyInteger('target_i');
            $table->unsignedTinyInteger('target_score');
            $table->decimal('target_exposure', 18, 2)->nullable();
            $table->timestamps();
            $table->unique(['risk_id', 'year', 'quarter']);
        });

        Schema::create('rmi_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('dimension', 30);
            $table->string('code', 20);
            $table->string('name', 255);
            $table->text('guidance')->nullable();
            $table->decimal('weight', 6, 2)->default(1);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('rmi_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('type', 20)->default('internal'); // internal (tahunan) | independent (paling sedikit 3 tahun sekali)
            $table->string('assessor', 160)->nullable();
            $table->date('assessed_at')->nullable();
            $table->string('status', 20)->default('draft');  // draft | final
            $table->decimal('score', 4, 2)->nullable();
            $table->string('phase', 30)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'year']);
        });

        Schema::create('rmi_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('rmi_assessments')->cascadeOnDelete();
            $table->foreignId('parameter_id')->constrained('rmi_parameters')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->nullable(); // 1-5
            $table->text('evidence')->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'parameter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rmi_scores');
        Schema::dropIfExists('rmi_assessments');
        Schema::dropIfExists('rmi_parameters');
        Schema::dropIfExists('risk_quarter_targets');
        Schema::table('risk_snapshots', fn (Blueprint $t) => $t->dropColumn('residual_exposure'));
        Schema::table('risks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scope_id');
            $table->dropColumn(['inherent_exposure', 'residual_exposure', 'target_exposure']);
        });
        Schema::table('scopes', fn (Blueprint $t) => $t->dropColumn(['kind', 'kks_type', 'transaction_value', 'decision_organ', 'decision_date', 'conclusion']));
        Schema::table('risk_categories', fn (Blueprint $t) => $t->dropColumn(['bumn_code', 'risk_limit']));
        Schema::table('criteria_versions', fn (Blueprint $t) => $t->dropColumn('scheme'));
    }
};
