<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\ReportController;
use App\Http\Middleware\RequireHousehold;
use Illuminate\Support\Facades\Route;

Route::get('/merek/{kind}/{filename}', [IdentityController::class, 'asset'])
    ->where(['kind' => 'logo|favicon', 'filename' => '[0-9a-f-]{36}\.(png|webp)'])->name('identity.asset');

Route::middleware('guest')->group(function () {
    Route::view('/signin', 'pages.auth.signin')->name('login');
    Route::post('/signin', [AccountController::class, 'login'])->middleware('throttle:8,1,signin:');
    Route::view('/signup', 'pages.auth.signup')->name('signup');
    Route::post('/signup', [AccountController::class, 'register'])->middleware('throttle:8,1,signup:');
    Route::view('/lupa-kata-sandi', 'pages.auth.forgot-password')->name('password.request');
    Route::post('/lupa-kata-sandi', [AccountController::class, 'sendResetLink'])->middleware('throttle:5,1,reset-link:')->name('password.email');
    Route::view('/reset-kata-sandi/{token}', 'pages.auth.reset-password')->name('password.reset');
    Route::post('/reset-kata-sandi', [AccountController::class, 'resetPassword'])->middleware('throttle:8,1,reset-password:')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/identitas', [IdentityController::class, 'index'])->name('identity.index');
    Route::post('/identitas', [IdentityController::class, 'update'])->name('identity.update');
    Route::post('/logout', [AccountController::class, 'logout'])->name('logout');
    Route::get('/mulai', [HouseholdController::class, 'onboarding'])->name('households.onboarding');
    Route::post('/mulai', [HouseholdController::class, 'store'])->name('households.store');
    Route::post('/mulai/gabung', [HouseholdController::class, 'join'])->middleware('throttle:5,1,household-join:')->name('households.join');
});

Route::middleware(['auth', RequireHousehold::class])->group(function () {
    Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/laporan/csv', [ReportController::class, 'export'])->name('reports.export');
    Route::prefix('catatan')->name('ledger.')->group(function () {
        Route::get('transaksi-cepat', [LedgerController::class, 'quickTransaction'])->name('quick-transaction');
        Route::get('audit', [LedgerController::class, 'audit'])->name('audit');
        Route::patch('wallets/{record}/saldo-awal', [LedgerController::class, 'correct'])->whereNumber('record')->name('correct');
        Route::where(['section' => 'wallets|categories|transactions', 'record' => '[0-9]+'])->group(function () {
            Route::get('{section}/{record?}', [LedgerController::class, 'index'])->name('index');
            Route::post('{section}/{record?}', [LedgerController::class, 'save'])->name('save');
            Route::delete('{section}/{record}', [LedgerController::class, 'remove'])->name('remove');
        });
    });
    Route::get('/', [HouseholdController::class, 'dashboard'])->name('dashboard');
    Route::redirect('/ecommerce', '/')->name('ecommerce');

    Route::prefix('rumah-tangga/{household}')->whereNumber('household')->name('households.')->group(function () {
        Route::get('/', [HouseholdController::class, 'settings'])->name('settings');
        Route::post('/kode', [HouseholdController::class, 'issueCode'])->middleware('throttle:5,1,household-invite:')->name('codes.store');
        Route::delete('/kode', [HouseholdController::class, 'revokeCode'])->name('codes.destroy');
        Route::delete('/pasangan/{partner}', [HouseholdController::class, 'revokePartner'])->whereNumber('partner')->name('partner.destroy');
        Route::delete('/keanggotaan', [HouseholdController::class, 'leave'])->name('leave');
    });

    // calendar pages
    Route::get('/calendar', function () {
        return view('pages.calendar', ['title' => 'Calendar']);
    })->name('calendar');
    // profile pages
    Route::get('/profile', function () {
        return view('pages.profile', ['title' => 'Profile']);
    })->name('profile');
    // form pages
    Route::get('/form-elements', function () {
        return view('pages.forms.form-elements', ['title' => 'Form Elements']);
    })->name('form-elements');
    // tables pages
    Route::get('/basic-tables', function () {
        return view('pages.tables.basic-tables', ['title' => 'Basic Tables']);
    })->name('basic-tables');
    // pages
    Route::get('/blank', function () {
        return view('pages.pages.blank', ['title' => 'Blank']);
    })->name('blank');
    // error pages
    Route::get('/error-404', function () {
        return view('pages.errors.error-404', ['title' => 'Error 404']);
    })->name('error-404');
    // chart pages
    Route::get('/line-chart', function () {
        return view('pages.chart.line-chart', ['title' => 'Line Chart']);
    })->name('line-chart');
    Route::get('/bar-chart', function () {
        return view('pages.chart.bar-chart', ['title' => 'Bar Chart']);
    })->name('bar-chart');
    // ui elements pages
    Route::get('/alerts', function () {
        return view('pages.ui-elements.alerts', ['title' => 'Alerts']);
    })->name('alerts');
    Route::get('/avatars', function () {
        return view('pages.ui-elements.avatars', ['title' => 'Avatars']);
    })->name('avatars');
    Route::get('/badge', function () {
        return view('pages.ui-elements.badge', ['title' => 'Badges']);
    })->name('badge');
    Route::get('/buttons', function () {
        return view('pages.ui-elements.buttons', ['title' => 'Buttons']);
    })->name('buttons');
    Route::get('/images', function () {
        return view('pages.ui-elements.images', ['title' => 'Images']);
    })->name('images');
    Route::get('/videos', function () {
        return view('pages.ui-elements.videos', ['title' => 'Videos']);
    })->name('videos');
    // legacy URLs
    Route::redirect('/badges', '/badge');
});
