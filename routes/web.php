<?php

use App\Http\Controllers\AuditTypeController;
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
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OfferFormTemplateController;
use App\Http\Controllers\OfferRequestController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/rejestracja', [RegistrationController::class, 'showForm'])->name('register.client');
Route::post('/rejestracja', [RegistrationController::class, 'register'])->name('register.client.store');

Route::get('/companies', function () {
    return redirect()->route('crm.index');
});

Route::post('/companies/fetch-gus', [CompanyController::class, 'fetchGus'])->name('companies.fetchGus');
Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])
    ->middleware('auth')
    ->name('companies.destroy');
Route::post('/companies/{company}/restore', [CompanyController::class, 'restore'])
    ->middleware('auth')
    ->name('companies.restore');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

Route::prefix('client')->name('client.')->middleware(['auth', 'client.role'])->group(function () {
    Route::get('/dashboard',     [ClientDashboardController::class,    'index'])->name('dashboard');
    Route::get('/audits',        [ClientAuditController::class,        'index'])->name('audits');
    Route::get('/offers',        [ClientOfferController::class,        'index'])->name('offers');
    Route::get('/offers/{offer}', [ClientOfferController::class,       'show'])->name('offers.show');
    Route::post('/offers/{offer}/accept', [ClientOfferController::class, 'accept'])->name('offers.accept');
    Route::post('/offers/{offer}/reject', [ClientOfferController::class, 'reject'])->name('offers.reject');
    Route::post('/offers/{offer}/negotiate', [ClientOfferController::class, 'negotiate'])->name('offers.negotiate');
    Route::get('/request-offer', [ClientOfferRequestController::class, 'index'])->name('request-offer');
    Route::post('/request-offer', [ClientOfferRequestController::class, 'store'])->name('request-offer.store');
    Route::get('/request-offer/{offerRequest}', [ClientOfferRequestController::class, 'show'])->name('request-offer.show');
    Route::get('/documents',     [ClientDocumentController::class,     'index'])->name('documents');
    Route::get('/chat',          [ClientChatController::class,         'index'])->name('chat');
    Route::post('/chat/send',    [ClientChatController::class,         'send'])->name('chat.send');
    Route::get('/chat/poll',     [ClientChatController::class,         'poll'])->name('chat.poll');
    Route::post('/chat/end',     [ClientChatController::class,         'endConversation'])->name('chat.end');
    Route::get('/users',                    [ClientUserController::class, 'index'])->middleware('client.admin')->name('users');
    Route::post('/users',                   [ClientUserController::class, 'store'])->middleware('client.admin')->name('users.store');
    Route::delete('/users/{user}',          [ClientUserController::class, 'destroy'])->middleware('client.admin')->name('users.destroy');
    Route::delete('/users/{user}/permanent',[ClientUserController::class, 'permanentDelete'])->middleware('client.admin')->name('users.permanent-delete');
});

Route::prefix('client-zone')->name('client-zone.')->middleware('auth')->group(function () {
    Route::get('/',  [ClientZoneController::class, 'index'])->name('index');
    Route::post('/impersonate/{company}', [ClientZoneController::class, 'impersonate'])->name('impersonate');
    Route::post('/stop', [ClientZoneController::class, 'stopImpersonate'])->name('stop');
    Route::get('/dashboard',     [ClientZoneController::class, 'dashboard'])->middleware('client.zone.session')->name('dashboard');
    Route::get('/audits',        [ClientZoneController::class, 'audits'])->middleware('client.zone.session')->name('audits');
    Route::get('/offers',        [ClientZoneController::class, 'offers'])->middleware('client.zone.session')->name('offers');
    Route::get('/request-offer', [ClientZoneController::class, 'requestOffer'])->middleware('client.zone.session')->name('request-offer');
    Route::get('/documents',     [ClientZoneController::class, 'documents'])->middleware('client.zone.session')->name('documents');
    Route::get('/chat',          [ClientZoneController::class, 'chat'])->middleware('client.zone.session')->name('chat');
    Route::get('/users',         [ClientZoneController::class, 'users'])->middleware('client.zone.session')->name('users');
});

Route::get('audit-types/versions/{version}/preview', [AuditTypeController::class, 'previewVersion'])->middleware('auth')->name('audit-types.versions.preview');

Route::middleware('auth')->group(function () {
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::post('/companies/{company}/accept', [CompanyController::class, 'accept'])->name('companies.accept');
    Route::post('/companies/{company}/users', [CompanyController::class, 'storeUser'])->name('companies.users.store');
    Route::post('/companies/{company}/assign-existing', [CompanyController::class, 'assignExisting'])->name('companies.users.assignExisting');
    Route::put('/companies/{company}/users/{user}', [CompanyController::class, 'updateUser'])->name('companies.users.update');
    Route::delete('/companies/{company}/users/{user}', [CompanyController::class, 'destroyUser'])->name('companies.users.destroy');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('audit-types', [AuditTypeController::class, 'index'])->name('audit-types.index');
    Route::get('audit-types/{auditType}', [AuditTypeController::class, 'show'])->name('audit-types.show');
    Route::post('audit-types/{auditType}/versions', [AuditTypeController::class, 'storeVersion'])->name('audit-types.versions.store');
    Route::post('audit-types/versions/{version}/set-current', [AuditTypeController::class, 'setAsCurrent'])->name('audit-types.versions.set-current');

    Route::prefix('offer-requests')->name('offer-requests.')->group(function () {
        Route::get('/create',                        [OfferRequestController::class, 'create'])->name('create');
        Route::post('/',                             [OfferRequestController::class, 'store'])->name('store');
        Route::get('/{offerRequest}',                [OfferRequestController::class, 'show'])->name('show');
        Route::patch('/{offerRequest}/status',       [OfferRequestController::class, 'updateStatus'])->name('update-status');
    });

    Route::prefix('offer-forms')->name('offer-forms.')->group(function () {
        Route::get('/',                              [OfferFormTemplateController::class, 'index'])->name('index');
        Route::post('/',                             [OfferFormTemplateController::class, 'store'])->name('store');
        Route::put('/{offerForm}',                   [OfferFormTemplateController::class, 'update'])->name('update');
        Route::delete('/{offerForm}',                [OfferFormTemplateController::class, 'destroy'])->name('destroy');
        Route::patch('/{offerForm}/toggle',          [OfferFormTemplateController::class, 'toggleActive'])->name('toggle');
    });

    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/',                                          [ChatController::class, 'index'])->name('index');
        Route::get('/{company}',                                 [ChatController::class, 'show'])->name('show');
        Route::post('/{company}/send',                           [ChatController::class, 'send'])->name('send');
        Route::get('/{company}/poll',                            [ChatController::class, 'poll'])->name('poll');
        Route::post('/{company}/end',                            [ChatController::class, 'endConversation'])->name('end');
        Route::get('/{company}/archive/{conversationId}',        [ChatController::class, 'archiveConversation'])->name('archive');
    });

    Route::prefix('offers')->name('offers.')->group(function () {
        Route::get('/template/{offer}',               [OfferController::class, 'getTemplate'])->name('template');
        Route::post('/ai-assist',                     [OfferController::class, 'aiAssist'])->name('ai-assist');
        Route::get('/',                              [OfferController::class, 'index'])->name('index');
        Route::get('/create',                        [OfferController::class, 'create'])->name('create');
        Route::get('/get-distance',                  [OfferController::class, 'getDistance'])->name('get-distance');
        Route::post('/',                             [OfferController::class, 'store'])->name('store');
        Route::get('/{offer}',                       [OfferController::class, 'show'])->name('show');
        Route::get('/{offer}/edit',                  [OfferController::class, 'edit'])->name('edit');
        Route::get('/{offer}/pdf',                   [OfferController::class, 'pdf'])->name('pdf');
        Route::put('/{offer}',                       [OfferController::class, 'update'])->name('update');
        Route::delete('/{offer}',                    [OfferController::class, 'destroy'])->name('destroy');
        Route::patch('/{offer}/status',              [OfferController::class, 'updateStatus'])->name('status');
        Route::post('/{offer}/messages',             [OfferController::class, 'storeMessage'])->name('messages.store');
        Route::post('/{offer}/save-as-template',     [OfferController::class, 'saveAsTemplate'])->name('save-as-template');
        Route::post('/{offer}/clone',                [OfferController::class, 'clone'])->name('clone');
        Route::patch('/{offer}/unit-prices',             [OfferController::class, 'updateUnitPrices'])->name('unit-prices');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', fn () => redirect()->route('settings.users.index'))->name('index');
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
        Route::get('company', [Settings\CompanySettingsController::class, 'index'])->name('company');
        Route::post('company', [Settings\CompanySettingsController::class, 'update'])->name('company.update');
        Route::post('company/sync-owner', [Settings\CompanySettingsController::class, 'syncOwner'])->name('company.sync-owner');
    });
});

Route::prefix('crm')->name('crm.')->middleware(['auth'])->group(function () {
    Route::get('/', [CrmController::class, 'index'])->name('index');
    Route::patch('/companies/{company}/dashboard', [CrmController::class, 'toggleDashboard'])->name('companies.dashboard');
    Route::patch('/companies/{company}/archive', [CrmController::class, 'archiveCompany'])->name('companies.archive');
    Route::patch('/companies/{company}/restore', [CrmController::class, 'restoreCompany'])->name('companies.restore');
    Route::delete('/companies/{company}', [CrmController::class, 'destroyCompany'])->name('companies.destroy');
    Route::post('/opportunities', [CrmController::class, 'storeOpportunity'])->name('opportunities.store');
    Route::patch('/opportunities/{opportunity}/stage', [CrmController::class, 'updateOpportunityStage'])->name('opportunities.stage');
    Route::patch('/opportunities/{opportunity}', [CrmController::class, 'updateOpportunity'])->name('opportunities.update');
    Route::delete('/opportunities/{opportunity}', [CrmController::class, 'destroyOpportunity'])->name('opportunities.destroy');
    Route::post('/tasks', [CrmController::class, 'storeTask'])->name('tasks.store');
    Route::put('/tasks/{task}', [CrmController::class, 'updateTask'])->name('tasks.update');
    Route::delete('/tasks/{task}', [CrmController::class, 'destroyTask'])->name('tasks.destroy');
    Route::patch('/tasks/{task}/status', [CrmController::class, 'updateTaskStatus'])->name('tasks.status');
    Route::delete('/orphaned-users/{assignmentId}', [CrmController::class, 'detachOrphanedUser'])->name('detach-orphaned-user');
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
        'data_uri_starts_with' => $base64 ? 'data:image/png;base64,' . substr($base64, 0, 30) : null,
        'controller_file_contains_new_code' => str_contains(
            file_get_contents(app_path('Http/Controllers/OfferController.php')),
            "base64_encode(file_get_contents(\$logoPath))"
        ),
    ], 200, [], JSON_PRETTY_PRINT);
})->middleware('auth');

require __DIR__.'/auth.php';
