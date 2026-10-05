<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 255);
            $table->foreignId('unit_id')->constrained('org_units');
            $table->foreignId('objective_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('process_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained('risk_categories');
            $table->foreignId('owner_id')->constrained('users');
            $table->text('cause');
            $table->text('event');
            $table->text('impact');
            $table->string('source_type', 20)->default('internal');   // internal | external
            $table->string('source_kind', 30)->default('process');    // people|process|technology|infrastructure|regulation|financial|third_party
            $table->text('existing_controls')->nullable();
            $table->string('treatment', 20)->default('reduce');       // avoid | reduce | share | retain
            $table->text('treatment_note')->nullable();
            $table->string('status', 30)->default('draft');           // draft | pending | treating | monitoring | closed
            $table->date('due_date')->nullable();
            $table->foreignId('criteria_version_id')->nullable()->constrained('criteria_versions')->nullOnDelete();
            // skor saat ini (denormalisasi dari versi terakhir yang disetujui)
            $table->unsignedTinyInteger('inherent_l')->default(3);
            $table->unsignedTinyInteger('inherent_i')->default(3);
            $table->json('inherent_dims')->nullable();
            $table->unsignedTinyInteger('residual_l')->default(2);
            $table->unsignedTinyInteger('residual_i')->default(3);
            $table->json('residual_dims')->nullable();
            $table->unsignedTinyInteger('target_l')->default(1);
            $table->unsignedTinyInteger('target_i')->default(3);
            $table->unsignedTinyInteger('inherent_score')->default(9);
            $table->unsignedTinyInteger('residual_score')->default(6);
            $table->unsignedTinyInteger('target_score')->default(3);
            $table->string('residual_level', 12)->default('medium');
            $table->string('evaluation', 20)->default('monitor');     // acceptable|monitor|treat|escalate|critical
            $table->string('trend', 10)->default('flat');             // up | flat | down
            $table->unsignedTinyInteger('previous_score')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('closed_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'unit_id', 'status']);
            $table->index(['organization_id', 'residual_score']);
        });

        Schema::create('risk_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedTinyInteger('inherent_l');
            $table->unsignedTinyInteger('inherent_i');
            $table->unsignedTinyInteger('residual_l');
            $table->unsignedTinyInteger('residual_i');
            $table->unsignedTinyInteger('target_l');
            $table->unsignedTinyInteger('target_i');
            $table->json('snapshot')->nullable();      // salinan field naratif saat versi dibuat
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['risk_id', 'version']);
        });

        Schema::create('risk_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('risk_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);                 // YYYY-MM
            $table->unsignedTinyInteger('inherent_score');
            $table->unsignedTinyInteger('residual_score');
            $table->string('level', 12);
            $table->string('status', 30);
            $table->timestamp('created_at');
            $table->unique(['risk_id', 'period']);
            $table->index(['organization_id', 'period']);
        });

        Schema::create('controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 255);
            $table->text('objective')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->string('frequency', 40)->default('monthly');
            $table->string('type', 20)->default('preventive');   // preventive | detective | corrective
            $table->string('mode', 20)->default('manual');       // manual | automated
            $table->unsignedTinyInteger('design_eff')->nullable();     // 1-4
            $table->unsignedTinyInteger('operating_eff')->nullable();  // 1-4
            $table->date('last_tested_at')->nullable();
            $table->date('next_test_at')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('control_risk', function (Blueprint $table) {
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->foreignId('risk_id')->constrained()->cascadeOnDelete();
            $table->primary(['control_id', 'risk_id']);
        });

        Schema::create('control_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->date('tested_at');
            $table->foreignId('tester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('design_eff');
            $table->unsignedTinyInteger('operating_eff');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('action_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->foreignId('risk_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->decimal('budget', 18, 2)->nullable();
            $table->string('priority', 12)->default('medium');   // low | medium | high | critical
            $table->date('start_date')->nullable();
            $table->date('due_date');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedTinyInteger('expected_dl')->default(0);  // penurunan kemungkinan yang diharapkan
            $table->unsignedTinyInteger('expected_di')->default(0);  // penurunan dampak yang diharapkan
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'due_date']);
        });

        Schema::create('action_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('from_pct');
            $table->unsignedTinyInteger('to_pct');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('kris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 255);
            $table->string('unit', 40)->nullable();
            $table->foreignId('risk_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');     // manual | api | import
            $table->string('frequency', 20)->default('monthly');
            $table->string('direction', 10)->default('up_bad');  // up_bad | down_bad
            $table->decimal('threshold_warn', 14, 2);
            $table->decimal('threshold_crit', 14, 2);
            $table->unsignedTinyInteger('decimals')->default(0);
            $table->decimal('last_value', 14, 2)->nullable();
            $table->string('status', 10)->default('normal');     // normal | warning | critical
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('kri_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kri_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->decimal('value', 14, 2);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_ref', 120)->nullable();
            $table->timestamps();
            $table->unique(['kri_id', 'period']);
        });
    }

    public function down(): void
    {
        foreach (['kri_values', 'kris', 'action_progress', 'action_plans', 'control_assessments', 'control_risk', 'controls', 'risk_snapshots', 'risk_versions', 'risks'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
