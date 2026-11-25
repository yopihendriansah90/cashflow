@extends('layouts.fullscreen-layout')

@section('document-language', 'id')

@section('content')
    <x-common.cashflow-auth heading="Mulai rumah tangga">
        @if (auth()->user()->is_system_admin)
            <x-ui.button as="a" variant="outline" size="lg" href="{{ route('identity.index') }}">{{ __('Pengaturan identitas') }}</x-ui.button>
        @endif
        @error('household')
            <p role="alert" class="text-sm text-error-700 dark:text-error-400">{{ $message }}</p>
        @enderror
        <form method="POST" action="{{ route('households.store') }}" class="space-y-4">
            @csrf
            <h2 class="font-semibold">{{ __('Buat rumah tangga') }}</h2>
            <x-common.cashflow-field name="name" label="Nama rumah tangga" :value="old('name')" maxlength="120" />
            <x-common.cashflow-submit>{{ __('Buat rumah tangga') }}</x-common.cashflow-submit>
        </form>
        <form method="POST" action="{{ route('households.join') }}" class="space-y-4 border-t border-gray-200 pt-5 dark:border-gray-800">
            @csrf
            <h2 class="font-semibold">{{ __('Gabung dengan pasangan') }}</h2>
            <x-common.cashflow-field name="code" label="Kode undangan" autocomplete="off" maxlength="32" spellcheck="false" autocapitalize="off" />
            <x-common.cashflow-submit>{{ __('Gabung') }}</x-common.cashflow-submit>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button type="submit" variant="outline" size="lg">{{ __('Keluar dari akun') }}</x-ui.button>
        </form>
    </x-common.cashflow-auth>
@endsection
