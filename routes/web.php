<?php

use App\Http\Controllers\Admin\ActivityMasterOptionController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\PeriodController;
use App\Http\Controllers\Admin\ThresholdController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\MemberDetailController;
use App\Http\Controllers\MyFollowUpController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressReportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WorkloadEntryController;
use App\Http\Controllers\WorkProgressController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware(['auth', 'audit.forbidden'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:5,1')->name('profile.password.update');

    Route::middleware('role:member')->group(function () {
        Route::get('/beban-kerja-saya', [WorkloadEntryController::class, 'show'])->name('workload.entry');
        Route::post('/beban-kerja-saya/aktivitas', [WorkloadEntryController::class, 'activity'])->name('workload.activity');
        Route::delete('/beban-kerja-saya/aktivitas/{activity}', [WorkloadEntryController::class, 'destroyActivity'])->name('workload.activity.destroy');
        Route::post('/beban-kerja-saya/progres', [WorkProgressController::class, 'store'])->name('workload.progress.store');
        Route::delete('/beban-kerja-saya/progres/{progress}', [WorkProgressController::class, 'destroy'])->name('workload.progress.destroy');
        Route::post('/beban-kerja-saya/ajukan', [WorkloadEntryController::class, 'submit'])->name('workload.submit');
    });
    Route::get('/anggota/{user}', MemberDetailController::class)->name('members.show');

    Route::get('/tindak-lanjut-saya', [MyFollowUpController::class, 'index'])->name('follow-ups.mine');
    Route::patch('/tindak-lanjut-saya/{action}/status', [MyFollowUpController::class, 'update'])->name('follow-ups.mine.update');

    Route::get('/laporan/cetak', [ReportController::class, 'print'])->name('reports.print');
    Route::get('/laporan/progres', ProgressReportController::class)->name('reports.progress');
    Route::get('/laporan/csv', [ReportController::class, 'csv'])->middleware('throttle:10,1')->name('reports.csv');
    Route::get('/laporan/xlsx', [ReportController::class, 'xlsx'])->middleware('throttle:10,1')->name('reports.xlsx');

    Route::middleware('role:manager,process_owner,administrator')->group(function () {
        Route::get('/validasi', [ReviewController::class, 'index'])->name('reviews.index');
        Route::put('/validasi/{submission}', [ReviewController::class, 'update'])->name('reviews.update');
        Route::get('/tindak-lanjut', [FollowUpController::class, 'index'])->name('follow-ups.index');
        Route::get('/tindak-lanjut/pengguna/{owner}/pengajuan', [FollowUpController::class, 'ownerSubmissions'])->name('follow-ups.owner-submissions');
        Route::post('/tindak-lanjut', [FollowUpController::class, 'store'])->name('follow-ups.store');
        Route::put('/tindak-lanjut/{action}', [FollowUpController::class, 'update'])->name('follow-ups.update');
    });

    Route::middleware('role:process_owner,administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/periode', [PeriodController::class, 'index'])->name('periods.index');
        Route::post('/periode', [PeriodController::class, 'store'])->name('periods.store');
        Route::put('/periode/{period}', [PeriodController::class, 'update'])->name('periods.update');
        Route::put('/periode/{period}/standar-kapasitas', [PeriodController::class, 'updateCapacityStandard'])->name('periods.capacity-standard.update');
        Route::post('/periode/{period}/buka', [PeriodController::class, 'open'])->name('periods.open');
        Route::post('/periode/{period}/kunci', [PeriodController::class, 'lock'])->name('periods.lock');
        Route::post('/periode/{period}/buka-kembali', [PeriodController::class, 'reopen'])->name('periods.reopen');
        Route::get('/ambang-utilisasi', [ThresholdController::class, 'index'])->name('thresholds.index');
        Route::post('/ambang-utilisasi', [ThresholdController::class, 'store'])->name('thresholds.store');
        Route::get('/audit', AuditController::class)->name('audit.index');
        Route::get('/master-aktivitas', [ActivityMasterOptionController::class, 'index'])->name('activity-master-options.index');
        Route::put('/master-aktivitas/{option}', [ActivityMasterOptionController::class, 'update'])->name('activity-master-options.update');
        Route::put('/master-aktivitas/{option}/status', [ActivityMasterOptionController::class, 'status'])->name('activity-master-options.status');
        Route::post('/master-aktivitas/{option}/merge', [ActivityMasterOptionController::class, 'merge'])->name('activity-master-options.merge');
    });

    Route::middleware('role:administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/organisasi', [OrganizationController::class, 'index'])->name('organization.index');
        Route::post('/unit', [OrganizationController::class, 'storeUnit'])->name('units.store');
        Route::put('/unit/{unit}', [OrganizationController::class, 'updateUnit'])->name('units.update');
        Route::post('/pengguna', [OrganizationController::class, 'storeUser'])->name('users.store');
        Route::put('/pengguna/{user}', [OrganizationController::class, 'updateUser'])->name('users.update');
    });
});
