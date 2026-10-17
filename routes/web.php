<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\BlockController;
use App\Http\Controllers\Admin\BudgetController as AdminBudgetController;
use App\Http\Controllers\Admin\CommonFacilityController;
use App\Http\Controllers\Admin\ConstructionStageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LotController;
use App\Http\Controllers\Admin\PemberkasanDocumentController as AdminPemberkasanDocumentController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Finance\FinanceCashPositionController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\Marketing\BookingController as MarketingBookingController;
use App\Http\Controllers\Marketing\CustomerController as MarketingCustomerController;
use App\Http\Controllers\Marketing\DashboardController as MarketingDashboardController;
use App\Http\Controllers\Marketing\LotController as MarketingLotController;
use App\Http\Controllers\Marketing\MarketingLeadActivityController;
use App\Http\Controllers\Marketing\MarketingLeadCalendarController;
use App\Http\Controllers\Marketing\MarketingLeadController;
use App\Http\Controllers\Marketing\MarketingLeadFollowUpController;
use App\Http\Controllers\Marketing\MarketingLeadReportController;
use App\Http\Controllers\Marketing\SalesController as MarketingSalesController;
use App\Http\Controllers\Marketing\SitePlanController as MarketingSitePlanController;
use App\Http\Controllers\Pembangunan\DashboardController as PembangunanDashboardController;
use App\Http\Controllers\Pembangunan\FacilityProgressController;
use App\Http\Controllers\Pembangunan\FinanceController as PembangunanFinanceController;
use App\Http\Controllers\Pembangunan\ProgressController;
use App\Http\Controllers\Pembangunan\PropertyOverviewController;
use App\Http\Controllers\Pemberkasan\AdditionalDocumentController;
use App\Http\Controllers\Pemberkasan\BookingController as PemberkasanBookingController;
use App\Http\Controllers\Pemberkasan\DashboardController as PemberkasanDashboardController;
use App\Http\Controllers\Reports\ProgressReportController;
use App\Http\Controllers\SitePlanCoordinatesController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', function () {
    $user = request()->user();

    if ($user->dashboardRouteName() === 'dashboard') {
        return view('dashboard');
    }

    return redirect()->route($user->dashboardRouteName());
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/tl-pembangunan', fn () => redirect()->route('pembangunan.dashboard'))
        ->middleware('role:tl_pembangunan')
        ->name('tl.pembangunan.dashboard');
    Route::get('/staff-pembangunan', fn () => redirect()->route('pembangunan.dashboard'))
        ->middleware('role:staff_pembangunan')
        ->name('staff.pembangunan.dashboard');
    Route::get('/tl-marketing', fn () => redirect()->route('marketing.dashboard'))
        ->middleware('role:tl_marketing')
        ->name('tl.marketing.dashboard');
    Route::get('/staff-marketing', fn () => redirect()->route('marketing.dashboard'))
        ->middleware('role:staff_marketing')
        ->name('staff.marketing.dashboard');
    Route::get('/tl-pemberkasan', fn () => redirect()->route('pemberkasan.dashboard'))
        ->middleware('role:tl_pemberkasan')
        ->name('tl.pemberkasan.dashboard');
    Route::get('/staff-pemberkasan', fn () => redirect()->route('pemberkasan.dashboard'))
        ->middleware('role:staff_pemberkasan')
        ->name('staff.pemberkasan.dashboard');
});

/*
|--------------------------------------------------------------------------
| Role Testing
|--------------------------------------------------------------------------
*/

Route::get('/test-admin', function () {
    return 'ADMIN ACCESS';
})->middleware([
    'auth',
    'role:admin',
])->name('test.admin');

Route::get('/test-marketing', function () {
    return 'MARKETING ACCESS';
})->middleware([
    'auth',
    'division:marketing',
])->name('test.marketing');

Route::get('/test-pembangunan', function () {
    return 'PEMBANGUNAN ACCESS';
})->middleware([
    'auth',
    'division:pembangunan',
])->name('test.pembangunan');

Route::get('/test-pemberkasan', function () {
    return 'PEMBERKASAN ACCESS';
})->middleware([
    'auth',
    'division:pemberkasan',
])->name('test.pemberkasan');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('/alerts/create', [AlertController::class, 'create'])->name('alerts.create');
    Route::post('/alerts', [AlertController::class, 'store'])->name('alerts.store');
});

Route::get('/admin', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:admin'])
    ->name('admin.dashboard');

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('users', UserController::class)
            ->except(['show']);

        Route::resource('budgets', AdminBudgetController::class)
            ->except(['show']);

        Route::resource('properties', PropertyController::class)
            ->except(['show']);
        Route::put('/properties/{property}/lots/{lot}/map-coordinates', [SitePlanCoordinatesController::class, 'update'])
            ->name('properties.lots.map-coordinates.update');

        Route::resource(
            'properties.blocks',
            BlockController::class
        )->except(['show']);

        Route::resource(
            'properties.blocks.lots',
            LotController::class
        )->except(['show']);

        Route::resource(
            'construction-stages',
            ConstructionStageController::class
        )->except(['show']);

        Route::get('/property-construction-stages', [PropertyController::class, 'constructionStages'])
            ->name('property-construction-stages.index');
        Route::post('/properties/{property}/construction-stages', [PropertyController::class, 'storeConstructionStage'])
            ->name('property-construction-stages.store');
        Route::delete('/properties/{property}/construction-stages/{constructionStage}', [PropertyController::class, 'destroyConstructionStage'])
            ->name('property-construction-stages.destroy');

        Route::resource(
            'common-facilities',
            CommonFacilityController::class
        )->except(['show']);

        Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
        Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });

Route::prefix('pembangunan')
    ->middleware([
        'auth',
        'verified',
        'division:pembangunan',
    ])
    ->name('pembangunan.')
    ->group(function () {
        Route::get('/dashboard', [PembangunanDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/properties', [PropertyOverviewController::class, 'index'])
            ->name('properties.index');

        Route::get('/progress', [ProgressController::class, 'index'])
            ->name('progress.index');

        Route::get('/progress/facilities', [FacilityProgressController::class, 'index'])
            ->name('progress.facilities.index');
        Route::get('/progress/facilities/{commonFacility}', [FacilityProgressController::class, 'show'])
            ->name('progress.facilities.show');
        Route::put('/progress/facilities/{commonFacility}', [FacilityProgressController::class, 'update'])
            ->name('progress.facilities.legacy-update');

        Route::get(
            '/properties/{property}/blocks/{block}/lots/{lot}/progress',
            [ProgressController::class, 'show']
        )->name('progress.show');

        Route::post(
            '/properties/{property}/blocks/{block}/lots/{lot}/progress',
            [ProgressController::class, 'store']
        )->name('progress.store');

        Route::put(
            '/properties/{property}/blocks/{block}/lots/{lot}/progress',
            [ProgressController::class, 'update']
        )->name('progress.update');

        Route::put(
            '/properties/{property}/blocks/{block}/lots/{lot}/progress/{constructionProgress}',
            [ProgressController::class, 'update']
        )->name('progress.records.update');

        Route::delete(
            '/properties/{property}/blocks/{block}/lots/{lot}/progress/{constructionProgress}',
            [ProgressController::class, 'destroy']
        )->name('progress.records.destroy');

        Route::post('/progress/facilities/{commonFacility}/progress', [FacilityProgressController::class, 'store'])
            ->name('progress.facilities.store');
        Route::put('/progress/facilities/{commonFacility}/progress', [FacilityProgressController::class, 'update'])
            ->name('progress.facilities.update');
        Route::delete('/progress/facilities/{commonFacility}/progress', [FacilityProgressController::class, 'destroy'])
            ->name('progress.facilities.destroy');

    });

Route::prefix('finance')
    ->middleware(['auth', 'verified', 'finance'])
    ->name('finance.')
    ->group(function () {
        Route::get('/', [PembangunanFinanceController::class, 'index'])->name('index');
        Route::resource('expense-transactions', PembangunanFinanceController::class)
            ->except(['index', 'show'])
            ->parameters(['expense-transactions' => 'expenseTransaction']);
    });

Route::prefix('keuangan')
    ->middleware(['auth', 'verified', 'division:finance'])
    ->name('keuangan.')
    ->group(function () {
        Route::get('/cash-position', [FinanceCashPositionController::class, 'index'])->name('cash-position.index');
        Route::post('/cash-accounts', [FinanceCashPositionController::class, 'storeAccount'])->name('cash-accounts.store');
        Route::put('/cash-accounts/{cashAccount}', [FinanceCashPositionController::class, 'updateAccount'])->name('cash-accounts.update');
        Route::post('/cash-flows', [FinanceCashPositionController::class, 'storeCashFlow'])->name('cash-flows.store');
        Route::delete('/cash-flows/{cashFlow}', [FinanceCashPositionController::class, 'destroyCashFlow'])->name('cash-flows.destroy');
    });

Route::prefix('marketing')
    ->middleware(['auth', 'verified', 'division:marketing'])
    ->name('marketing.')
    ->group(function () {
        Route::get('/dashboard', [MarketingDashboardController::class, 'index'])->name('dashboard');
        Route::get('/properties/{property}/site-plan', [MarketingSitePlanController::class, 'edit'])
            ->name('site-plans.edit');
        Route::put('/properties/{property}/site-plan', [MarketingSitePlanController::class, 'update'])
            ->name('site-plans.update');
        Route::put('/properties/{property}/lots/{lot}/map-coordinates', [SitePlanCoordinatesController::class, 'update'])
            ->name('site-plans.coordinates.update');
        Route::get('/lots', [MarketingLotController::class, 'index'])->name('lots.index');
        Route::resource('sales', MarketingSalesController::class)->except(['show']);
        Route::resource('leads', MarketingLeadController::class)->except(['show']);
        Route::get('/leads/{lead}', [MarketingLeadController::class, 'show'])->name('leads.show');
        Route::post('/leads/{lead}/restore', [MarketingLeadController::class, 'restore'])->name('leads.restore');
        Route::post('/leads/{lead}/calls', [MarketingLeadActivityController::class, 'storeCall'])->name('leads.calls.store');
        Route::post('/leads/{lead}/surveys', [MarketingLeadActivityController::class, 'storeSurvey'])->name('leads.surveys.store');
        Route::put('/leads/{lead}/surveys/{survey}', [MarketingLeadActivityController::class, 'updateSurvey'])->name('leads.surveys.update');
        Route::post('/leads/{lead}/follow-ups', [MarketingLeadFollowUpController::class, 'store'])->name('leads.follow-ups.store');
        Route::put('/leads/{lead}/follow-ups/{followUp}', [MarketingLeadFollowUpController::class, 'update'])->name('leads.follow-ups.update');
        Route::get('/follow-ups', [MarketingLeadFollowUpController::class, 'index'])->name('follow-ups.index');
        Route::get('/calendar', MarketingLeadCalendarController::class)->name('calendar');
        Route::get('/reports/crm', MarketingLeadReportController::class)->name('reports.crm');
        Route::resource('customers', MarketingCustomerController::class);
        Route::resource('bookings', MarketingBookingController::class);
        Route::get('/bookings/{booking}/documents/edit', [MarketingBookingController::class, 'editDocuments'])
            ->name('bookings.documents.edit');
        Route::put('/bookings/{booking}/documents', [MarketingBookingController::class, 'updateDocuments'])
            ->name('bookings.documents.update');
        Route::post('/bookings/{booking}/documents', [PemberkasanBookingController::class, 'uploadDocument'])
            ->name('bookings.documents.store');
        Route::post('/documents/{pemberkasanDocument}/retry', [PemberkasanBookingController::class, 'retryUpload'])
            ->name('bookings.documents.retry');
        Route::post('/bookings/{booking}/payments', [MarketingBookingController::class, 'storePayment'])
            ->name('bookings.payments.store');
    });

Route::prefix('pemberkasan')
    ->middleware(['auth', 'verified', 'division:pemberkasan'])
    ->name('pemberkasan.')
    ->group(function () {
        Route::get('/dashboard', [PemberkasanDashboardController::class, 'index'])->name('dashboard');
        Route::get('/bookings', [PemberkasanBookingController::class, 'index'])->name('bookings.index');
        Route::get('/other-documents', [AdditionalDocumentController::class, 'index'])->name('other-documents.index');
        Route::post('/other-documents', [AdditionalDocumentController::class, 'store'])->name('other-documents.store');
        Route::post('/bookings/{booking}/documents', [PemberkasanBookingController::class, 'uploadDocument'])->name('bookings.documents.store');
        Route::post('/bookings/{booking}/documents/sync', [PemberkasanBookingController::class, 'syncCustomerDocuments'])->name('bookings.documents.sync');
        Route::post('/documents/{pemberkasanDocument}/retry', [PemberkasanBookingController::class, 'retryUpload'])->name('bookings.documents.retry');
        Route::get('/bookings/{booking}', [PemberkasanBookingController::class, 'show'])->name('bookings.show');
        Route::put('/bookings/{booking}', [PemberkasanBookingController::class, 'update'])->name('bookings.update');
    });

Route::prefix('reports')
    ->middleware(['auth', 'verified'])
    ->name('reports.')
    ->group(function () {
        Route::get('/progress', [ProgressReportController::class, 'index'])->name('progress.index');
        Route::get('/progress/export.xlsx', [ProgressReportController::class, 'export'])->name('progress.export');
        Route::get('/progress/create', [ProgressReportController::class, 'create'])->name('progress.create');
        Route::post('/progress', [ProgressReportController::class, 'store'])->name('progress.store');
        Route::get('/progress/{progressReport}/edit', [ProgressReportController::class, 'edit'])->name('progress.edit');
        Route::put('/progress/{progressReport}', [ProgressReportController::class, 'update'])->name('progress.update');
        Route::post('/progress/{progressReport}/submit', [ProgressReportController::class, 'submit'])->name('progress.submit');
        Route::post('/progress/{progressReport}/review', [ProgressReportController::class, 'review'])->name('progress.review');
    });

Route::middleware(['auth', 'verified', 'division:pemberkasan'])->group(function () {
    Route::get('/admin/google-drive/documents', [AdminPemberkasanDocumentController::class, 'index'])
        ->name('admin.google-drive.documents.index');
    Route::get('/admin/google-drive/customers/{customer}/documents', [AdminPemberkasanDocumentController::class, 'customerDocuments'])
        ->name('admin.google-drive.documents.customer');
    Route::post('/admin/google-drive/documents/{pemberkasanDocument}/retry', [AdminPemberkasanDocumentController::class, 'retry'])
        ->name('admin.google-drive.documents.retry');
    Route::put('/admin/google-drive/documents/{pemberkasanDocument}', [AdminPemberkasanDocumentController::class, 'replace'])
        ->name('admin.google-drive.documents.replace');
    Route::delete('/admin/google-drive/documents/{pemberkasanDocument}', [AdminPemberkasanDocumentController::class, 'destroy'])
        ->name('admin.google-drive.documents.destroy');
});

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/google-drive/redirect', [GoogleDriveController::class, 'redirect'])
        ->name('google.drive.redirect');

    Route::get('/google-drive/callback', [GoogleDriveController::class, 'callback'])
        ->name('google.drive.callback');
});

require __DIR__.'/settings.php';
