<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\BlockController;
use App\Http\Controllers\Admin\CommonFacilityController;
use App\Http\Controllers\Admin\ConstructionStageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LotController;
use App\Http\Controllers\Admin\PemberkasanDocumentController as AdminPemberkasanDocumentController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\Marketing\BookingController as MarketingBookingController;
use App\Http\Controllers\Marketing\CustomerController as MarketingCustomerController;
use App\Http\Controllers\Marketing\DashboardController as MarketingDashboardController;
use App\Http\Controllers\Marketing\LotController as MarketingLotController;
use App\Http\Controllers\Marketing\SalesController as MarketingSalesController;
use App\Http\Controllers\Pembangunan\BudgetController;
use App\Http\Controllers\Pembangunan\CommonFacilityController as PembangunanCommonFacilityController;
use App\Http\Controllers\Pembangunan\DashboardController as PembangunanDashboardController;
use App\Http\Controllers\Pembangunan\FinanceController as PembangunanFinanceController;
use App\Http\Controllers\Pembangunan\ProgressController;
use App\Http\Controllers\Pemberkasan\BookingController as PemberkasanBookingController;
use App\Http\Controllers\Pemberkasan\DashboardController as PemberkasanDashboardController;
use App\Http\Controllers\Reports\ProgressReportController;
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

        Route::resource('properties', PropertyController::class)
            ->except(['show']);

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

        Route::resource('properties', PropertyController::class)
            ->except(['show']);
        Route::get('/construction-stages', [PropertyController::class, 'constructionStages'])
            ->name('construction-stages.index');
        Route::post('/properties/{property}/construction-stages', [PropertyController::class, 'storeConstructionStage'])
            ->name('properties.construction-stages.store');
        Route::delete('/properties/{property}/construction-stages/{constructionStage}', [PropertyController::class, 'destroyConstructionStage'])
            ->name('properties.construction-stages.destroy');

        Route::resource('properties.blocks', BlockController::class)
            ->except(['show']);

        Route::resource('properties.blocks.lots', LotController::class)
            ->except(['show']);

        Route::get('/dashboard', [PembangunanDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/progress', [ProgressController::class, 'index'])
            ->name('progress.index');

        Route::get(
            '/properties/{property}/blocks/{block}/lots/{lot}/progress',
            [ProgressController::class, 'show']
        )->name('progress.show');

        Route::put(
            '/properties/{property}/blocks/{block}/lots/{lot}/progress',
            [ProgressController::class, 'update']
        )->name('progress.update');

        Route::get(
            '/common-facilities',
            [PembangunanCommonFacilityController::class, 'index']
        )->name('common-facilities.index');

        Route::get(
            '/common-facilities/{commonFacility}',
            [PembangunanCommonFacilityController::class, 'show']
        )->name('common-facilities.show');

        Route::put(
            '/common-facilities/{commonFacility}',
            [PembangunanCommonFacilityController::class, 'update']
        )->name('common-facilities.update');

    });

Route::prefix('finance')
    ->middleware(['auth', 'verified', 'finance'])
    ->name('finance.')
    ->group(function () {
        Route::get('/', [PembangunanFinanceController::class, 'index'])->name('index');
        Route::get('/budgets/create', [BudgetController::class, 'create'])->name('budgets.create');
        Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');
        Route::resource('expense-transactions', PembangunanFinanceController::class)
            ->except(['index', 'show'])
            ->parameters(['expense-transactions' => 'expenseTransaction']);
    });

Route::prefix('marketing')
    ->middleware(['auth', 'verified', 'division:marketing'])
    ->name('marketing.')
    ->group(function () {
        Route::get('/dashboard', [MarketingDashboardController::class, 'index'])->name('dashboard');
        Route::get('/lots', [MarketingLotController::class, 'index'])->name('lots.index');
        Route::resource('sales', MarketingSalesController::class)->except(['show']);
        Route::resource('customers', MarketingCustomerController::class);
        Route::resource('bookings', MarketingBookingController::class);
    });

Route::prefix('pemberkasan')
    ->middleware(['auth', 'verified', 'division:pemberkasan'])
    ->name('pemberkasan.')
    ->group(function () {
        Route::get('/dashboard', [PemberkasanDashboardController::class, 'index'])->name('dashboard');
        Route::get('/bookings', [PemberkasanBookingController::class, 'index'])->name('bookings.index');
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
