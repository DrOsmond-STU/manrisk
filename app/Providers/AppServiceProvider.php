<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Model::preventLazyLoading(!$this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());
        Password::defaults(fn () => Password::min((int) config('manrisk.password_min'))->mixedCase()->numbers()->symbols());
        Relation::enforceMorphMap([
            'action_plan' => \App\Models\ActionPlan::class, 'action_progress' => \App\Models\ActionProgress::class, 'ai_interaction' => \App\Models\AiInteraction::class, 'alert' => \App\Models\Alert::class, 'approval' => \App\Models\Approval::class, 'approval_step' => \App\Models\ApprovalStep::class, 'audit_log' => \App\Models\AuditLog::class, 'auth_log' => \App\Models\AuthLog::class, 'consultation' => \App\Models\Consultation::class, 'context_factor' => \App\Models\ContextFactor::class, 'control' => \App\Models\Control::class, 'control_assessment' => \App\Models\ControlAssessment::class, 'criteria_version' => \App\Models\CriteriaVersion::class, 'document' => \App\Models\Document::class, 'framework_item' => \App\Models\FrameworkItem::class, 'improvement' => \App\Models\Improvement::class, 'incident' => \App\Models\Incident::class, 'kri' => \App\Models\Kri::class, 'kri_value' => \App\Models\KriValue::class, 'lesson' => \App\Models\Lesson::class, 'loss_event' => \App\Models\LossEvent::class, 'objective' => \App\Models\Objective::class, 'org_unit' => \App\Models\OrgUnit::class, 'organization' => \App\Models\Organization::class, 'process' => \App\Models\Process::class, 'program' => \App\Models\Program::class, 'report_job' => \App\Models\ReportJob::class, 'review' => \App\Models\Review::class, 'risk' => \App\Models\Risk::class, 'risk_category' => \App\Models\RiskCategory::class, 'risk_snapshot' => \App\Models\RiskSnapshot::class, 'risk_version' => \App\Models\RiskVersion::class, 'scope' => \App\Models\Scope::class, 'user' => \App\Models\User::class,
        ]);
        Vite::useBuildDirectory('build');
        // Batas permintaan umum per pengguna (mencegah penyalahgunaan/scraping massal)
        RateLimiter::for('app', fn (Request $r) => Limit::perMinute(300)->by($r->user()?->id ?: $r->ip()));
        if ($this->app->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
