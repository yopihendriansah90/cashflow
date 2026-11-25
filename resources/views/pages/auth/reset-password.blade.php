@extends('layouts.fullscreen-layout')

@section('document-language', 'id')

@section('content')
    <x-common.cashflow-auth heading="Atur ulang kata sandi">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">
            @error('token')
                <p role="alert" class="text-sm text-error-700 dark:text-error-400">{{ $message }}</p>
            @enderror
            <x-common.cashflow-field name="email" label="Email" type="email" :value="old('email', request()->query('email'))" autocomplete="email" />
            <x-common.cashflow-field name="password" label="Kata sandi baru" type="password" autocomplete="new-password" minlength="8" />
            <x-common.cashflow-field name="password_confirmation" label="Ulangi kata sandi" type="password" autocomplete="new-password" minlength="8" />
            <x-common.cashflow-submit>{{ __('Simpan kata sandi') }}</x-common.cashflow-submit>
        </form>
    </x-common.cashflow-auth>
@endsection
