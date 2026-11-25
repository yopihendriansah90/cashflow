@extends('layouts.fullscreen-layout')

@section('document-language', 'id')

@section('content')
    <x-common.cashflow-auth heading="Daftar akun">
        <form method="POST" action="{{ route('signup') }}" class="space-y-4">
            @csrf
            <x-common.cashflow-field name="name" label="Nama" :value="old('name')" autocomplete="name" maxlength="255" />
            <x-common.cashflow-field name="email" label="Email" type="email" :value="old('email')" autocomplete="email" />
            <x-common.cashflow-field name="password" label="Kata sandi" type="password" autocomplete="new-password" minlength="8" />
            <x-common.cashflow-field name="password_confirmation" label="Ulangi kata sandi" type="password" autocomplete="new-password" minlength="8" />
            <x-common.cashflow-submit>{{ __('Daftar akun') }}</x-common.cashflow-submit>
        </form>
        <x-ui.button as="a" variant="outline" size="lg" href="{{ route('login') }}">{{ __('Kembali ke halaman masuk') }}</x-ui.button>
    </x-common.cashflow-auth>
@endsection
