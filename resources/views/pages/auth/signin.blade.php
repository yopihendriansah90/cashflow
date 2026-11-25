@extends('layouts.fullscreen-layout')

@section('document-language', 'id')

@section('content')
    <x-common.cashflow-auth heading="Masuk">
        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <x-common.cashflow-field name="email" label="Email" type="email" :value="old('email')" autocomplete="email" />
            <x-common.cashflow-field name="password" label="Kata sandi" type="password" autocomplete="current-password" />
            <x-common.cashflow-submit>{{ __('Masuk') }}</x-common.cashflow-submit>
        </form>
        <div class="grid gap-2 sm:grid-cols-2">
            <x-ui.button as="a" variant="outline" size="lg" href="{{ route('signup') }}">{{ __('Daftar akun') }}</x-ui.button>
            <x-ui.button as="a" variant="outline" size="lg" href="{{ route('password.request') }}">{{ __('Lupa kata sandi?') }}</x-ui.button>
        </div>
    </x-common.cashflow-auth>
@endsection
