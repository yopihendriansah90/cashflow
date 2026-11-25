<?php

use App\Models\User;
use Illuminate\Support\Str;

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cashflow:admin {email} {--revoke}', function () {
    $user = User::where('email', Str::lower($this->argument('email')))->first();
    if (! $user) {
        $this->error(__('Akun tidak ditemukan.'));
        return 1;
    }

    $user->forceFill(['is_system_admin' => ! $this->option('revoke')])->save();
    $this->info(__('Izin pengelola diperbarui.'));

    return 0;
})->purpose('Tetapkan atau cabut pengelola identitas sistem');
