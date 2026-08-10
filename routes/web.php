<?php

use App\Http\Controllers\AuditTypeController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Client\AuditController as ClientAuditController;
use App\Http\Controllers\Client\ChatController as ClientChatController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\DocumentController as ClientDocumentController;
use App\Http\Controllers\Client\OfferController as ClientOfferController;
use App\Http\Controllers\Client\OfferRequestController as ClientOfferRequestController;
use App\Http\Controllers\Client\RegistrationController;
use App\Http\Controllers\Client\UserController as ClientUserController;
use App\Http\Controllers\ClientZoneController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OfferFormTemplateController;
use App\Http\Controllers\OfferRequestController;
use App\Http\Controllers\PriceCatalogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicSurveyController;
use App\Http\Controllers\Settings;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPageController::class, 'show'])->name('home');
Route::get('/branding/logo', [BrandingController::class, 'logo'])->name('branding.logo');

Route::get('/rejestracja', [RegistrationController::class, 'showForm'])->name('register.client');
Route::post('/rejestracja', [RegistrationController::class, 'register'])->middleware('throttle:5,1')->name('register.client.store');

// Publiczna ankieta dla klienta końcowego (bez logowania, white-label)
Route::get('/f/{token}', [PublicSurveyController::class, 'show'])->middleware('app.module:audits')->name('public.survey.show');
Route::post('/f/{token}', [PublicSurveyController::class, 'submit'])->middleware(['app.module:audits', 'throttle:20,1'])->name('public.survey.submit');
Route::post('/f/{token}/pdf', [PublicSurveyController::class, 'pdf'])->middleware(['app.module:audits', 'throttle:10,1'])->name('public.survey.pdf');

Route::get('/companies', function () {
    return redirect()->route('crm.index');
})->middleware('app.module:crm');

Route::post('/companies/fetch-gus', [CompanyController::class, 'fetchGus'])
    ->middleware(['auth', 'staff.role', 'app.module:crm'])
    ->name('companies.fetchGus');
Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])
    ->middleware(['auth', 'staff.role', 'app.module:crm'])
    ->name('companies.destroy');
Route::post('/companies/{company}/restore', [CompanyController::class, 'restore'])
    ->middleware(['auth', 'staff.role', 'app.module:crm'])
    ->name('companies.restore');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'staff.role', 'app.module:dashboard'])->name('dashboard');

Route::prefix('client')->name('client.')->middleware(['auth', 'client.role', 'app.module:client_zone'])->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class,    'index'])->name('dashboard');
    Route::get('/audits', [ClientAuditController::class, 'index'])->middleware('app.module:audits')->name('audits');
    Route::get('/offers', [ClientOfferController::class, 'index'])->middleware('app.module:offers')->name('offers');
    Route::get('/offers/{offer}', [ClientOfferController::class, 'show'])->middleware('app.module:offers')->name('offers.show');
    Route::post('/offers/{offer}/accept', [ClientOfferController::class, 'accept'])->middleware('app.module:offers')->name('offers.accept');
    Route::post('/offers/{offer}/reject', [ClientOfferController::class, 'reject'])->middleware('app.module:offers')->name('offers.reject');
    Route::post('/offers/{offer}/negotiate', [ClientOfferController::class, 'negotiate'])->middleware('app.module:offers')->name('offers.negotiate');
    Route::get('/request-offer', [ClientOfferRequestController::class, 'index'])->middleware('app.module:offers')->name('request-offer');
    Route::post('/request-offer', [ClientOfferRequestController::class, 'store'])->middleware('app.module:offers')->name('request-offer.store');
    Route::get('/request-offer/{offerRequest}', [ClientOfferRequestController::class, 'show'])->middleware('app.module:offers')->name('request-offer.show');
    Route::get('/documents', [ClientDocumentController::class, 'index'])->middleware('app.module:documents')->name('documents');
    Route::get('/chat', [ClientChatController::class, 'index'])->middleware('app.module:client_zone')->name('chat');
    Route::post('/chat/send', [ClientChatController::class, 'send'])->middleware('app.module:client_zone')->name('chat.send');
    Route::get('/chat/poll', [ClientChatController::class, 'poll'])->middleware('app.module:client_zone')->name('chat.poll');
    Route::post('/chat/end', [ClientChatController::class, 'endConversation'])->middleware('app.module:client_zone')->name('chat.end');
    Route::get('/users', [ClientUserController::class, 'index'])->middleware(['client.admin', 'app.module:client_zone'])->name('users');
    Route::post('/users', [ClientUserController::class, 'store'])->middleware(['client.admin', 'app.module:client_zone'])->name('users.store');
    Route::delete('/users/{user}', [ClientUserController::class, 'destroy'])->middleware(['client.admin', 'app.module:client_zone'])->name('users.destroy');
    Route::delete('/users/{user}/permanent', [ClientUserController::class, 'permanentDelete'])->middleware(['client.admin', 'app.module:client_zone'])->name('users.permanent-delete');
});

Route::prefix('client-zone')->name('client-zone.')->middleware(['auth', 'staff.role', 'full.staff', 'app.module:client_zone'])->group(function () {
    Route::get('/', [ClientZoneController::class, 'index'])->name('index');
    Route::post('/impersonate/{company}', [ClientZoneController::class, 'impersonate'])->name('impersonate');
    Route::post('/stop', [ClientZoneController::class, 'stopImpersonate'])->name('stop');
    Route::get('/dashboard', [ClientZoneController::class, 'dashboard'])->middleware('client.zone.session')->name('dashboard');
    Route::get('/audits', [ClientZoneController::class, 'audits'])->middleware(['client.zone.session', 'app.module:audits'])->name('audits');
    Route::get('/offers', [ClientZoneController::class, 'offers'])->middleware(['client.zone.session', 'app.module:offers'])->name('offers');
    Route::get('/request-offer', [ClientZoneController::class, 'requestOffer'])->middleware(['client.zone.session', 'app.module:offers'])->name('request-offer');
    Route::get('/documents', [ClientZoneController::class, 'documents'])->middleware(['client.zone.session', 'app.module:documents'])->name('documents');
    Route::get('/chat', [ClientZoneController::class, 'chat'])->middleware(['client.zone.session', 'app.module:client_zone'])->name('chat');
    Route::get('/users', [ClientZoneController::class, 'users'])->middleware(['client.zone.session', 'app.module:client_zone'])->name('users');
});

Route::get('audit-types/versions/{version}/preview', [AuditTypeController::class, 'previewVersion'])->middleware(['auth', 'staff.role', 'app.module:audits'])->name('audit-types.versions.preview');

Route::middleware(['auth', 'staff.role'])->group(function () {
    Route::middleware('app.module:crm')->group(function () {
        Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
        Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::post('/companies/{company}/accept', [CompanyController::class, 'accept'])->name('companies.accept');
        Route::post('/companies/{company}/users', [CompanyController::class, 'storeUser'])->name('companies.users.store');
        Route::post('/companies/{company}/assign-existing', [CompanyController::class, 'assignExisting'])->name('companies.users.assignExisting');
        Route::put('/companies/{company}/users/{user}', [CompanyController::class, 'updateUser'])->name('companies.users.update');
        Route::delete('/companies/{company}/users/{user}', [CompanyController::class, 'destroyUser'])->name('companies.users.destroy');
        Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    });
    Route::get('/profile', [ProfileController::class, 'edit'])->withoutMiddleware('staff.role')->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->withoutMiddleware('staff.role')->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->withoutMiddleware('staff.role')->name('profile.destroy');

    Route::get('audit-types', [AuditTypeController::class, 'index'])->middleware('app.module:audits')->name('audit-types.index');
    Route::get('audit-types/{auditType}', [AuditTypeController::class, 'show'])->middleware('app.module:audits')->name('audit-types.show');
    Route::post('audit-types/{auditType}/versions', [AuditTypeController::class, 'storeVersion'])->middleware('app.module:audits')->name('audit-types.versions.store');
    Route::post('audit-types/versions/{version}/set-current', [AuditTypeController::class, 'setAsCurrent'])->middleware('app.module:audits')->name('audit-types.versions.set-current');

    Route::prefix('offer-requests')->name('offer-requests.')->middleware('app.module:offers')->group(function () {
        Route::get('/create', [OfferRequestController::class, 'create'])->name('create');
        Route::post('/', [OfferRequestController::class, 'store'])->name('store');
        Route::get('/{offerRequest}', [OfferRequestController::class, 'show'])->name('show');
        Route::get('/{offerRequest}/edit', [OfferRequestController::class, 'edit'])->name('edit');
        Route::put('/{offerRequest}', [OfferRequestController::class, 'update'])->name('update');
        Route::patch('/{offerRequest}/status', [OfferRequestController::class, 'updateStatus'])->name('update-status');
        Route::delete('/{offerRequest}', [OfferRequestController::class, 'destroy'])->name('destroy');
        Route::post('/{offerRequest}/public-link', [OfferRequestController::class, 'savePublic'])->name('save-public');
        Route::get('/{offerRequest}/pdf', [OfferRequestController::class, 'pdf'])->name('pdf');
    });

    Route::prefix('offer-forms')->name('offer-forms.')->middleware('app.module:offers')->group(function () {
        Route::get('/', [OfferFormTemplateController::class, 'index'])->name('index');
        Route::post('/', [OfferFormTemplateController::class, 'store'])->name('store');
        Route::put('/{offerForm}', [OfferFormTemplateController::class, 'update'])->name('update');
        Route::delete('/{offerForm}', [OfferFormTemplateController::class, 'destroy'])->name('destroy');
        Route::patch('/{offerForm}/toggle', [OfferFormTemplateController::class, 'toggleActive'])->name('toggle');
    });

    Route::prefix('pricing-catalog')->name('pricing-catalog.')->middleware(['staff.role', 'app.module:offers'])->group(function () {
        Route::get('/', [PriceCatalogController::class, 'index'])->name('index');
        Route::post('/', [PriceCatalogController::class, 'store'])->name('store');
        Route::put('/{priceCatalogItem}', [PriceCatalogController::class, 'update'])->name('update');
        Route::patch('/{priceCatalogItem}/toggle', [PriceCatalogController::class, 'toggle'])->name('toggle');
    });

    Route::prefix('chat')->name('chat.')->middleware('app.module:client_zone')->group(function () {
        Route::get('/', [ChatController::class, 'index'])->name('index');
        Route::get('/{company}', [ChatController::class, 'show'])->name('show');
        Route::post('/{company}/send', [ChatController::class, 'send'])->name('send');
        Route::get('/{company}/poll', [ChatController::class, 'poll'])->name('poll');
        Route::post('/{company}/end', [ChatController::class, 'endConversation'])->name('end');
        Route::get('/{company}/archive/{conversationId}', [ChatController::class, 'archiveConversation'])->name('archive');
    });

    Route::prefix('offers')->name('offers.')->middleware('app.module:offers')->group(function () {
        Route::get('/template/{offer}', [OfferController::class, 'getTemplate'])->name('template');
        Route::post('/ai-assist', [OfferController::class, 'aiAssist'])->name('ai-assist');
        Route::get('/', [OfferController::class, 'index'])->name('index');
        Route::get('/create', [OfferController::class, 'create'])->name('create');
        Route::get('/get-distance', [OfferController::class, 'getDistance'])->name('get-distance');
        Route::post('/', [OfferController::class, 'store'])->name('store');
        Route::get('/{offer}', [OfferController::class, 'show'])->name('show');
        Route::get('/{offer}/edit', [OfferController::class, 'edit'])->name('edit');
        Route::get('/{offer}/pdf', [OfferController::class, 'pdf'])->name('pdf');
        Route::get('/{offer}/word', [OfferController::class, 'downloadWord'])->name('download-word');
        Route::post('/{offer}/save-to-storage', [OfferController::class, 'saveToStorage'])->name('save-to-storage');
        Route::put('/{offer}', [OfferController::class, 'update'])->name('update');
        Route::delete('/{offer}', [OfferController::class, 'destroy'])->name('destroy');
        Route::patch('/{offer}/status', [OfferController::class, 'updateStatus'])->name('status');
        Route::post('/{offer}/messages', [OfferController::class, 'storeMessage'])->name('messages.store');
        Route::post('/{offer}/save-as-template', [OfferController::class, 'saveAsTemplate'])->name('save-as-template');
        Route::post('/{offer}/clone', [OfferController::class, 'clone'])->name('clone');
        Route::patch('/{offer}/unit-prices', [OfferController::class, 'updateUnitPrices'])->name('unit-prices');
    });

    Route::prefix('settings')->name('settings.')->middleware('full.staff')->group(function () {
        Route::get('/', fn () => redirect()->route('settings.users.index'))->name('index');
        Route::get('archive', [Settings\ArchiveController::class, 'index'])->name('archive.index');
        Route::get('roles', [Settings\RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [Settings\RoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [Settings\RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [Settings\RoleController::class, 'destroy'])->name('roles.destroy');
        Route::resource('users', Settings\UserController::class)->names('users')->except('destroy');
        Route::delete('users/{user}', [Settings\UserController::class, 'destroy'])
            ->middleware('auth')
            ->name('users.destroy')
            ->withTrashed();
        Route::post('users/{user}/restore', [Settings\UserController::class, 'restore'])
            ->name('users.restore')
            ->withTrashed();
        Route::delete('users/{user}/force-destroy', [Settings\UserController::class, 'forceDestroy'])
            ->middleware('auth')
            ->name('users.forceDestroy')
            ->withTrashed();
        Route::post('users/{user}/assign-company', [Settings\UserController::class, 'assignCompany'])
            ->middleware('auth')
            ->name('users.assignCompany');
        Route::post('users/{user}/assign-to-company', [Settings\UserController::class, 'assignToCompany'])
            ->middleware('auth')
            ->name('users.assign-to-company')
            ->withTrashed();
        Route::get('users/{user}/auditor-access', [Settings\UserController::class, 'showAuditorAccess'])
            ->name('users.auditor-access');
        Route::post('users/{user}/auditor-access', [Settings\UserController::class, 'storeAuditorAccess'])
            ->name('users.auditor-access.store');
        Route::patch('users/{user}/auditor-access/{access}', [Settings\UserController::class, 'updateAuditorAccess'])
            ->name('users.auditor-access.update');
        Route::delete('users/{user}/auditor-access/{access}', [Settings\UserController::class, 'destroyAuditorAccess'])
            ->name('users.auditor-access.destroy');
        Route::post('users/{user}/auditor-documents', [Settings\UserController::class, 'assignAuditorDocument'])
            ->name('users.auditor-documents');
        Route::middleware('superadmin.only')->group(function () {
            Route::get('company', [Settings\CompanySettingsController::class, 'index'])->name('company');
            Route::post('company', [Settings\CompanySettingsController::class, 'update'])->name('company.update');
            Route::post('company/sync-owner', [Settings\CompanySettingsController::class, 'syncOwner'])->name('company.sync-owner');
        });
    });

    Route::prefix('documents')->name('documents.')->middleware('app.module:documents')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::post('/', [DocumentController::class, 'store'])->name('store');
        Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
        Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
    });
});

Route::prefix('crm')->name('crm.')->middleware(['auth', 'staff.role', 'app.module:crm'])->group(function () {
    Route::get('/', [CrmController::class, 'index'])->name('index');
    Route::patch('/companies/{company}/dashboard', [CrmController::class, 'toggleDashboard'])->name('companies.dashboard');
    Route::patch('/companies/{company}/archive', [CrmController::class, 'archiveCompany'])->name('companies.archive');
    Route::patch('/companies/{company}/restore', [CrmController::class, 'restoreCompany'])->name('companies.restore');
    Route::delete('/companies/{company}', [CrmController::class, 'destroyCompany'])->name('companies.destroy');
    Route::post('/opportunities', [CrmController::class, 'storeOpportunity'])->name('opportunities.store');
    Route::patch('/opportunities/{opportunity}/stage', [CrmController::class, 'updateOpportunityStage'])->name('opportunities.stage');
    Route::patch('/opportunities/{opportunity}', [CrmController::class, 'updateOpportunity'])->name('opportunities.update');
    Route::post('/opportunities/{opportunity}/duplicate', [CrmController::class, 'duplicateOpportunity'])->name('opportunities.duplicate');
    Route::post('/opportunities/{opportunity}/attach-offer', [CrmController::class, 'attachOffer'])->name('opportunities.attach-offer');
    Route::delete('/opportunities/{opportunity}', [CrmController::class, 'destroyOpportunity'])->name('opportunities.destroy');
    Route::post('/tasks', [CrmController::class, 'storeTask'])->name('tasks.store');
    Route::put('/tasks/{task}', [CrmController::class, 'updateTask'])->name('tasks.update');
    Route::delete('/tasks/{task}', [CrmController::class, 'destroyTask'])->name('tasks.destroy');
    Route::patch('/tasks/{task}/status', [CrmController::class, 'updateTaskStatus'])->name('tasks.status');
    Route::delete('/orphaned-users/{assignmentId}', [CrmController::class, 'detachOrphanedUser'])->name('detach-orphaned-user');
});

Route::get('/public/project-gantt/{token}', [ProjectController::class, 'publicGantt'])->name('projects.public-gantt');

Route::prefix('projects')->name('projects.')->middleware(['auth', 'staff.role', 'app.module:projects'])->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('index');
    Route::post('/', [ProjectController::class, 'store'])->name('store');
    Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
    Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
    Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');
    Route::post('/{project}/tasks', [ProjectController::class, 'storeTask'])->name('tasks.store');
    Route::post('/{project}/tasks/reorder', [ProjectController::class, 'reorderTasks'])->name('tasks.reorder');
    Route::delete('/{project}/tasks/bulk', [ProjectController::class, 'bulkDestroyTasks'])->name('tasks.bulk-destroy');
    Route::get('/{project}/gantt/export', [ProjectController::class, 'exportGantt'])->name('gantt.export');
    Route::post('/{project}/gantt/import', [ProjectController::class, 'importGantt'])->name('gantt.import');
    Route::patch('/{project}/tasks/{task}', [ProjectController::class, 'updateTask'])->name('tasks.update');
    Route::delete('/{project}/tasks/{task}', [ProjectController::class, 'destroyTask'])->name('tasks.destroy');
    Route::post('/{project}/public-gantt', [ProjectController::class, 'generatePublicGantt'])->name('public-gantt.generate');
    Route::post('/{project}/finances', [ProjectController::class, 'storeFinancialEntry'])->name('finances.store');
    Route::post('/{project}/finances/import', [ProjectController::class, 'importFinancialEntries'])->name('finances.import');
    Route::post('/{project}/finances/bulk', [ProjectController::class, 'bulkUpdateFinancialEntries'])->name('finances.bulk');
    Route::patch('/{project}/finances/{entry}/status', [ProjectController::class, 'updateFinancialEntryStatus'])->name('finances.status');
    Route::patch('/{project}/finances/{entry}', [ProjectController::class, 'updateFinancialEntry'])->name('finances.update');
    Route::delete('/{project}/finances/{entry}', [ProjectController::class, 'destroyFinancialEntry'])->name('finances.destroy');
    Route::post('/{project}/finance-groups', [ProjectController::class, 'storeFinanceGroup'])->name('finance-groups.store');
    Route::delete('/{project}/finance-groups/{group}', [ProjectController::class, 'destroyFinanceGroup'])->name('finance-groups.destroy');
    Route::post('/{project}/requirements', [ProjectController::class, 'storeRequirement'])->name('requirements.store');
    Route::get('/{project}/requirements/template', [ProjectController::class, 'downloadRequirementsTemplate'])->name('requirements.template');
    Route::post('/{project}/requirements/import', [ProjectController::class, 'importRequirements'])->name('requirements.import');
    Route::post('/{project}/requirements/pdf/preview', [ProjectController::class, 'previewRequirementsPdf'])->name('requirements.pdf.preview');
    Route::post('/{project}/requirements/pdf/confirm', [ProjectController::class, 'confirmRequirementsPdf'])->name('requirements.pdf.confirm');
    Route::patch('/{project}/requirements/{requirement}/status', [ProjectController::class, 'updateRequirementStatus'])->name('requirements.status');
    Route::patch('/{project}/requirements/{requirement}', [ProjectController::class, 'updateRequirement'])->name('requirements.update');
    Route::delete('/{project}/requirements/{requirement}', [ProjectController::class, 'destroyRequirement'])->name('requirements.destroy');
    Route::post('/{project}/documents', [ProjectController::class, 'storeDocument'])->name('documents.store');
    Route::get('/{project}/documents/{document}', [ProjectController::class, 'downloadDocument'])->name('documents.download');
    Route::delete('/{project}/documents/{document}', [ProjectController::class, 'destroyDocument'])->name('documents.destroy');
});

Route::prefix('suppliers')->name('suppliers.')->middleware(['auth', 'staff.role', 'app.module:crm'])->group(function () {
    Route::get('/', [SupplierController::class, 'index'])->name('index');
    Route::get('/{supplier}', [SupplierController::class, 'show'])->name('show');
    Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
});

// Session check endpoint (used by session-expired modal JS)
Route::get('/session-check', function () {
    return response()->json(['authenticated' => auth()->check()]);
})->name('session.check');

Route::get('/debug-logo2', function () {
    $logoPath = public_path('Logo2.png');
    $exists = file_exists($logoPath);
    $base64 = $exists ? base64_encode(file_get_contents($logoPath)) : null;

    return response()->json([
        'public_logo_exists' => $exists,
        'public_logo_size' => $exists ? filesize($logoPath) : null,
        'generated_base64_length' => $base64 ? strlen($base64) : null,
        'data_uri_starts_with' => $base64 ? 'data:image/png;base64,'.substr($base64, 0, 30) : null,
        'controller_file_contains_new_code' => str_contains(
            file_get_contents(app_path('Http/Controllers/OfferController.php')),
            'base64_encode(file_get_contents($logoPath))'
        ),
    ], 200, [], JSON_PRETTY_PRINT);
})->middleware(['auth', 'staff.role']);

require __DIR__.'/auth.php';
