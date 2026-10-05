<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('code', 20)->unique();
            $table->string('logo_path')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->string('name', 160);
            $table->string('code', 20);
            $table->string('type', 40)->default('unit');
            $table->string('path', 500)->nullable();   // "/1/5/12/" untuk pencarian sub-unit
            $table->unsignedSmallInteger('level')->default(0);
            $table->unsignedBigInteger('head_user_id')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'parent_id']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 160);
            $table->string('password');
            $table->string('role', 40)->default('risk_officer');
            $table->string('position', 120)->nullable();
            $table->json('scope_units')->nullable();   // id unit tambahan yang boleh diakses
            $table->boolean('active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->json('preferences')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'email']);
        });

        Schema::create('password_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password');
            $table->timestamp('created_at');
        });

        Schema::create('objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 255);
            $table->string('kpi', 255)->nullable();
            $table->string('period', 20)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('objective_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->string('name', 255);
            $table->decimal('budget', 18, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('risk_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('risk_categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('name_en', 120)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('appetite')->default(6);
            $table->unsignedTinyInteger('tolerance')->default(9);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('criteria_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->date('effective_from');
            $table->json('likelihood');   // [{v,n,label,desc}]
            $table->json('impact');       // [{v,n,label,dims:{fin,ops,rep,law,...}}]
            $table->json('dimensions');   // [{key,label}]
            $table->json('matrix');       // {"L-I": "low|medium|high|very_high"}
            $table->json('thresholds');   // {escalate:16, critical:20}
            $table->boolean('active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'version']);
        });

        Schema::create('scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('objective')->nullable();
            $table->text('boundaries')->nullable();
            $table->string('period', 40)->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->text('area')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('context_factors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scope_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10);          // internal | external
            $table->string('factor', 120);
            $table->text('condition');
            $table->string('nature', 20);        // strength | weakness | opportunity | threat
            $table->timestamps();
        });

        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scope_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 255);
            $table->date('held_on');
            $table->text('participants')->nullable();
            $table->text('decisions')->nullable();
            $table->string('status', 20)->default('planned');  // planned | done
            $table->timestamps();
        });

        Schema::create('framework_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('clause', 10);       // 4.a, 5.2, 6.3
            $table->string('group', 20);        // principle | framework | process
            $table->string('title', 160);
            $table->string('status', 20)->default('partial'); // met | partial | unmet
            $table->unsignedTinyInteger('score')->nullable();   // 0-100 utk kerangka
            $table->string('module', 40)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'clause']);
        });
    }

    public function down(): void
    {
        foreach (['framework_items', 'consultations', 'context_factors', 'scopes', 'criteria_versions', 'risk_categories', 'processes', 'programs', 'objectives', 'password_history', 'users', 'org_units', 'organizations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
