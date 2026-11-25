@extends('layouts.fullscreen-layout')

@section('document-language', 'id')

@section('content')
    <x-common.cashflow-auth heading="Lupa kata sandi">
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <x-common.cashflow-field name="email" label="Email" type="email" :value="old('email')" autocomplete="email" />
            <x-common.cashflow-submit>{{ __('Kirim tautan reset') }}</x-common.cashflow-submit>
        </form>
        <x-ui.button as="a" variant="outline" size="lg" href="{{ route('login') }}">{{ __('Kembali ke halaman masuk') }}</x-ui.button>
    </x-common.cashflow-auth>
@endsection
