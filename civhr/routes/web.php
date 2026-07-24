<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\BalanceController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\LdController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 | One-time database setup for shell-less hosting. Set SETUP_TOKEN in .env,
 | visit /setup/<token> once to build tables + seed, then blank SETUP_TOKEN.
 | Idempotent (safe to re-run); returns plain-text output.
 */
Route::get('/setup/{token}', function (string $token) {
    $expected = (string) config('app.setup_token');
    abort_unless($expected !== '' && hash_equals($expected, $token), 404);

    Artisan::call('migrate', ['--force' => true]);
    $migrate = Artisan::output();
    Artisan::call('db:seed', ['--force' => true]);
    $seed = Artisan::output();

    return response(
        "== migrate ==\n{$migrate}\n== db:seed ==\n{$seed}\n".
        "DONE. Now remove SETUP_TOKEN from .env (set it blank) to disable this page.",
        200
    )->header('Content-Type', 'text/plain');
});

Route::get('/', function () {
    // Signed-in users go straight to the app; guests see the landing page.
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    $asset = fn (?string $p) => $p && file_exists(public_path($p)) ? asset($p) : null;

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'agency' => [
            'unit'      => config('agency.unit'),
            'name'      => config('agency.name'),
            'address'   => config('agency.address'),
            'address2'  => config('agency.address2'),
            'logoLeft'  => $asset(config('agency.logo_left')),
            'logoRight' => $asset(config('agency.logo_right')),
        ],
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    // Admins may preview the app as a plain employee and switch back.
    Route::post('/view-mode', function () {
        $mode = session('view_mode') === 'employee' ? 'admin' : 'employee';
        session(['view_mode' => $mode]);

        return redirect()->route('dashboard');
    })->name('view-mode.toggle');

    // Dashboard status trackers (admin-only).
    Route::get('/dashboard/employee/{employee}', [DashboardController::class, 'showEmployee'])
        ->name('dashboard.employee');
    Route::patch('/dashboard/ipcr/{employee}', [DashboardController::class, 'toggleIpcr'])
        ->middleware('admin')->name('dashboard.ipcr');
    Route::post('/dashboard/ld/{employee}', [DashboardController::class, 'storeLd'])
        ->middleware('admin')->name('dashboard.ld');
    Route::post('/dashboard/credit/{employee}', [DashboardController::class, 'adjustCredit'])
        ->middleware('admin')->name('dashboard.credit');

    // Breeze account settings (email / password)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Employee self-service profile (HR record)
    Route::get('/my-profile', [EmployeeProfileController::class, 'edit'])->name('my-profile.edit');
    Route::patch('/my-profile', [EmployeeProfileController::class, 'update'])->name('my-profile.update');

    // Leave — CS Form No. 6
    Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::get('/leave/requests', [LeaveController::class, 'requests'])->name('leave.requests');
    Route::get('/leave/create', [LeaveController::class, 'create'])->name('leave.create');
    Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::get('/leave/{application}', [LeaveController::class, 'show'])->name('leave.show');
    Route::get('/leave/{application}/print', [LeaveController::class, 'print'])->name('leave.print');
    Route::patch('/leave/{application}/save', [LeaveController::class, 'saveDraft'])->name('leave.save');
    Route::post('/leave/{application}/decide', [LeaveController::class, 'decide'])->name('leave.decide');
    Route::patch('/leave/{application}/recommender', [LeaveController::class, 'setRecommender'])->name('leave.recommender');
    Route::post('/leave/{application}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');

    // The wet-signed CS Form 6, uploaded by the applicant after approval.
    Route::post('/leave/{application}/signed-form', [LeaveController::class, 'storeSignedForm'])->name('leave.signed-form.store');
    Route::get('/leave/{application}/signed-form', [LeaveController::class, 'signedForm'])->name('leave.signed-form');

    // L&D — employee submits with photo proof; admin approves; files are
    // private and served only to the owner and admins.
    Route::post('/ld', [LdController::class, 'store'])->name('ld.store');
    Route::patch('/ld/{entry}/decide', [LdController::class, 'decide'])->name('ld.decide');
    Route::get('/ld/{entry}/file/{kind}', [LdController::class, 'file'])
        ->whereIn('kind', ['certificate', 'photo'])->name('ld.file');
});

// Admin area (superadmin + admin only)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
    Route::patch('users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::patch('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');

    // Non-working days that 6.C skips; updated yearly from the proclamation.
    Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

    // The all-employee leave balance grid, editable in place.
    Route::get('balances', [BalanceController::class, 'index'])->name('balances.index');
    Route::patch('balances/{employee}', [BalanceController::class, 'update'])->name('balances.update');
});

require __DIR__.'/auth.php';