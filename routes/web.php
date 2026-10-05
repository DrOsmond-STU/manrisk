<?php

use App\Http\Controllers\ActionPlanController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\ControlController;
use App\Http\Controllers\CriteriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ImprovementController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\KriController;
use App\Http\Controllers\MatrixController;
use App\Http\Controllers\ObjectiveController;
use App\Http\Controllers\OrgUnitController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
});

Route::middleware(['auth', 'session.policy'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::middleware(['auth', 'session.policy', 'password.fresh'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/executive', [DashboardController::class, 'executive'])->middleware('role:super_admin,risk_admin,risk_manager,management,auditor')->name('dashboard.executive');

    // Organisasi
    Route::get('/organization/units', [OrgUnitController::class, 'index'])->name('units.index');
    Route::post('/organization/units', [OrgUnitController::class, 'store'])->name('units.store');
    Route::put('/organization/units/{unit}', [OrgUnitController::class, 'update'])->name('units.update');
    Route::delete('/organization/units/{unit}', [OrgUnitController::class, 'destroy'])->name('units.destroy');
    Route::get('/organization/objectives', [ObjectiveController::class, 'index'])->name('objectives.index');
    Route::post('/organization/objectives', [ObjectiveController::class, 'store'])->name('objectives.store');
    Route::put('/organization/objectives/{objective}', [ObjectiveController::class, 'update'])->name('objectives.update');
    Route::delete('/organization/objectives/{objective}', [ObjectiveController::class, 'destroy'])->name('objectives.destroy');
    Route::post('/organization/programs', [ObjectiveController::class, 'storeProgram'])->name('programs.store');
    Route::put('/organization/programs/{program}', [ObjectiveController::class, 'updateProgram'])->name('programs.update');
    Route::delete('/organization/programs/{program}', [ObjectiveController::class, 'destroyProgram'])->name('programs.destroy');
    Route::post('/organization/processes', [ObjectiveController::class, 'storeProcess'])->name('processes.store');
    Route::put('/organization/processes/{process}', [ObjectiveController::class, 'updateProcess'])->name('processes.update');
    Route::delete('/organization/processes/{process}', [ObjectiveController::class, 'destroyProcess'])->name('processes.destroy');

    // Konteks & kriteria
    Route::get('/context', [ContextController::class, 'index'])->name('context.index');
    Route::post('/context/scopes', [ContextController::class, 'storeScope'])->name('scopes.store');
    Route::put('/context/scopes/{scope}', [ContextController::class, 'updateScope'])->name('scopes.update');
    Route::delete('/context/scopes/{scope}', [ContextController::class, 'destroyScope'])->name('scopes.destroy');
    Route::post('/context/factors', [ContextController::class, 'storeFactor'])->name('factors.store');
    Route::put('/context/factors/{factor}', [ContextController::class, 'updateFactor'])->name('factors.update');
    Route::delete('/context/factors/{factor}', [ContextController::class, 'destroyFactor'])->name('factors.destroy');
    Route::post('/context/consultations', [ContextController::class, 'storeConsultation'])->name('consultations.store');
    Route::put('/context/consultations/{consultation}', [ContextController::class, 'updateConsultation'])->name('consultations.update');
    Route::delete('/context/consultations/{consultation}', [ContextController::class, 'destroyConsultation'])->name('consultations.destroy');
    Route::get('/criteria', [CriteriaController::class, 'index'])->name('criteria.index');
    Route::post('/criteria', [CriteriaController::class, 'store'])->name('criteria.store');
    Route::post('/criteria/{criteria}/activate', [CriteriaController::class, 'activate'])->name('criteria.activate');
    Route::post('/criteria/categories', [CriteriaController::class, 'storeCategory'])->name('categories.store');
    Route::put('/criteria/categories/{category}', [CriteriaController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/criteria/categories/{category}', [CriteriaController::class, 'destroyCategory'])->name('categories.destroy');

    // Risiko
    Route::get('/risks/matrix', [MatrixController::class, 'index'])->name('risks.matrix');
    Route::get('/risks/evaluation', [MatrixController::class, 'evaluation'])->name('risks.evaluation');
    Route::get('/risks/residual', [MatrixController::class, 'residual'])->name('risks.residual');
    Route::resource('risks', RiskController::class);
    Route::post('/risks/{risk}/submit', [RiskController::class, 'submit'])->name('risks.submit');
    Route::post('/risks/{risk}/close', [RiskController::class, 'close'])->name('risks.close');
    Route::post('/risks/{risk}/lessons', [RiskController::class, 'lesson'])->name('risks.lesson');

    // Persetujuan
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/{approval}/decide', [ApprovalController::class, 'decide'])->name('approvals.decide');

    // Kontrol
    Route::resource('controls', ControlController::class)->except(['create', 'edit']);
    Route::post('/controls/{control}/assess', [ControlController::class, 'assess'])->name('controls.assess');

    // Action plan
    Route::resource('action-plans', ActionPlanController::class)->except(['create', 'edit'])->parameters(['action-plans' => 'plan']);
    Route::post('/action-plans/{plan}/progress', [ActionPlanController::class, 'progress'])->name('action-plans.progress');
    Route::post('/action-plans/{plan}/cancel', [ActionPlanController::class, 'cancel'])->name('action-plans.cancel');

    // KRI
    Route::resource('kris', KriController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/kris/{kri}/values', [KriController::class, 'value'])->name('kris.value');

    // Insiden & kerugian
    Route::get('/incidents/losses', [IncidentController::class, 'losses'])->name('losses.index');
    Route::post('/incidents/losses', [IncidentController::class, 'storeLoss'])->name('losses.store');
    Route::put('/incidents/losses/{loss}', [IncidentController::class, 'updateLoss'])->name('losses.update');
    Route::delete('/incidents/losses/{loss}', [IncidentController::class, 'destroyLoss'])->name('losses.destroy');
    Route::resource('incidents', IncidentController::class)->except(['create', 'edit']);
    Route::post('/incidents/{incident}/lessons', [IncidentController::class, 'lesson'])->name('incidents.lesson');

    // Review
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::post('/reviews/snapshot', [ReviewController::class, 'snapshot'])->name('reviews.snapshot');

    // Dokumen
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // Perbaikan berkelanjutan & kerangka ISO
    Route::get('/improvements', [ImprovementController::class, 'index'])->name('improvements.index');
    Route::post('/improvements', [ImprovementController::class, 'store'])->name('improvements.store');
    Route::put('/improvements/{improvement}', [ImprovementController::class, 'update'])->name('improvements.update');
    Route::delete('/improvements/{improvement}', [ImprovementController::class, 'destroy'])->name('improvements.destroy');
    Route::post('/lessons', [ImprovementController::class, 'storeLesson'])->name('lessons.store');
    Route::delete('/lessons/{lesson}', [ImprovementController::class, 'destroyLesson'])->name('lessons.destroy');
    Route::get('/framework', [ImprovementController::class, 'framework'])->name('framework.index');
    Route::put('/framework/{item}', [ImprovementController::class, 'updateFramework'])->name('framework.update');

    // Peringatan
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('/alerts/latest', [AlertController::class, 'latest'])->name('alerts.latest');
    Route::post('/alerts/read-all', [AlertController::class, 'readAll'])->name('alerts.read-all');
    Route::post('/alerts/{alert}/read', [AlertController::class, 'read'])->name('alerts.read');
    Route::post('/alerts/{alert}/handle', [AlertController::class, 'handle'])->name('alerts.handle');

    // Laporan & AI
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports', [ReportController::class, 'generate'])->middleware('throttle:30,1')->name('reports.generate');
    Route::get('/ai', [AiController::class, 'index'])->name('ai.index');
    Route::post('/ai', [AiController::class, 'run'])->middleware('throttle:20,1')->name('ai.run');

    // Pengaturan
    Route::get('/profile', [SettingsController::class, 'profile'])->name('profile');
    Route::put('/profile', [SettingsController::class, 'updateProfile'])->name('profile.update');
    Route::get('/settings/organization', [SettingsController::class, 'organization'])->name('settings.organization');
    Route::put('/settings/organization', [SettingsController::class, 'updateOrganization'])->name('settings.organization.update');

    // Administrasi
    Route::prefix('admin')->middleware('role:super_admin,risk_admin,risk_manager,auditor')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    });
    Route::prefix('admin')->middleware('role:super_admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset');
        Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
