<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->dateTime('occurred_at');
            $table->string('location', 160)->nullable();
            $table->foreignId('risk_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->string('title', 255);
            $table->text('chronology')->nullable();
            $table->text('cause')->nullable();
            $table->text('impact')->nullable();
            $table->decimal('loss_amount', 18, 2)->default(0);
            $table->string('loss_type', 40)->nullable();
            $table->text('response')->nullable();
            $table->text('corrective_action')->nullable();
            $table->string('status', 20)->default('reported');   // reported | investigating | corrective | closed
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'occurred_at']);
        });

        Schema::create('loss_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('risk_categories')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('risk_name', 255);
            $table->string('event', 255);
            $table->decimal('amount', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('period_type', 20);   // monthly | quarterly | semester | annual | adhoc
            $table->string('period', 20);        // 2026-Q3
            $table->foreignId('risk_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('previous_score')->nullable();
            $table->unsignedTinyInteger('current_score');
            $table->string('trend', 10);
            $table->text('note')->nullable();
            $table->string('decision', 20)->default('continue');  // continue | change_treatment | close | escalate
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'period']);
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('type', 30);            // new_risk | score_change | treatment | retain | closure | criteria
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('requester_id')->constrained('users');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->unsignedTinyInteger('total_steps')->default(3);
            $table->string('status', 20)->default('pending');   // pending | revision | approved | rejected | cancelled
            $table->text('note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step_no');
            $table->string('role', 40);
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20)->nullable();   // approve | revise | reject
            $table->text('note')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('type', 40)->default('evidence');
            $table->string('title', 255);
            $table->unsignedInteger('version')->default(1);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->string('hash', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expires_at')->nullable();
            $table->string('status', 20)->default('draft');   // draft | review | approved | expired
            $table->foreignId('replaces_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('improvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('source_type', 40);      // incident | audit | control_failure | kri_breach | treatment | trend | lesson
            $table->string('source_ref', 120)->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open');   // open | in_progress | done
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('text');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);          // kri_breach | score_up | action_overdue | approval_due | doc_expiring | control_due | incident
            $table->string('severity', 12);      // info | warning | critical
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->string('link', 255)->nullable();
            $table->string('dedupe_key', 160)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
            $table->unique(['organization_id', 'dedupe_key']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);          // created | updated | deleted | restored | custom
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label', 160)->nullable();
            $table->json('changes')->nullable();   // {field: [old, new]}
            $table->string('context', 160)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at');
            $table->index(['subject_type', 'subject_id']);
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('auth_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 160)->nullable();
            $table->string('event', 30);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('detail', 255)->nullable();
            $table->timestamp('created_at');
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 30);
            $table->string('prompt_hash', 64);
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->string('model', 80)->nullable();
            $table->boolean('ok')->default(true);
            $table->timestamp('created_at');
            $table->index(['organization_id', 'user_id', 'created_at']);
        });

        Schema::create('report_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('format', 10);
            $table->json('params')->nullable();
            $table->string('status', 20)->default('queued');   // queued | running | done | failed
            $table->string('path', 500)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['report_jobs', 'ai_interactions', 'auth_logs', 'audit_logs', 'alerts', 'lessons', 'improvements', 'documents', 'approval_steps', 'approvals', 'reviews', 'loss_events', 'incidents'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
