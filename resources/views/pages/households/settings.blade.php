@extends('layouts.cashflow')

@section('content')
    <x-common.page-breadcrumb :page-title="__('Pengaturan rumah tangga')">
        @if ($membership->role === \App\Models\HouseholdMembership::OWNER && ! $partner)
            <x-slot:actions>
                <div x-data x-init="@if ($code) $nextTick(() => $refs.codeDialog.showModal()) @endif">
                    <button type="button" x-ref="trigger" @click="$refs.codeDialog.showModal()"
                        class="min-h-11 rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white hover:bg-brand-700 dark:hover:bg-brand-500">
                        {{ $hasCode ? __('Kelola kode undangan') : __('Buat kode undangan') }}
                    </button>
                    <dialog x-ref="codeDialog" aria-labelledby="code-title" @close="$refs.trigger.focus()"
                        class="fixed inset-x-0 top-auto bottom-0 m-0 max-h-[85dvh] w-full max-w-none overflow-y-auto rounded-t-2xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] text-gray-900 backdrop:bg-gray-950/60 dark:bg-gray-900 dark:text-gray-100 sm:inset-0 sm:m-auto sm:w-md sm:max-w-full sm:rounded-2xl
                            motion-safe:translate-y-2 motion-safe:opacity-0 motion-safe:transition-[opacity,translate,display,overlay] motion-safe:transition-discrete motion-safe:duration-180
                            motion-safe:open:translate-y-0 motion-safe:open:opacity-100 motion-safe:starting:open:translate-y-2 motion-safe:starting:open:opacity-0
                            motion-safe:backdrop:opacity-0 motion-safe:backdrop:transition-[opacity,display,overlay] motion-safe:backdrop:transition-discrete motion-safe:backdrop:duration-180
                            motion-safe:open:backdrop:opacity-100 motion-safe:starting:open:backdrop:opacity-0">
                        <div class="space-y-4">
                            <h2 id="code-title" class="text-lg font-semibold">{{ __('Kode undangan') }}</h2>
                            @if ($code)
                                <p>{{ __('Salin dan bagikan kode ini kepada pasangan. Kode ini ditampilkan sekali dan berlaku 24 jam.') }}</p>
                                <input type="text" readonly aria-label="{{ __('Kode undangan') }}"
                                    value="{{ $code }}" @focus="$el.select()"
                                    class="min-h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-2 font-mono text-xs dark:border-gray-700 dark:bg-gray-800">
                            @elseif ($hasCode)
                                <p>{{ __('Kode undangan aktif. Membuat kode baru akan mencabut kode sebelumnya.') }}</p>
                            @else
                                <p>{{ __('Kode berlaku 24 jam dan hanya bisa dipakai sekali.') }}</p>
                            @endif
                            <div class="flex flex-wrap gap-2">
                                @unless ($code)
                                    <form method="POST" action="{{ route('households.codes.store', $household->id) }}"
                                        x-data="{ pending: false }" @submit="if (pending) $event.preventDefault(); pending = true">
                                        @csrf
                                        <x-common.cashflow-submit ::disabled="pending">{{ __('Buat kode undangan') }}</x-common.cashflow-submit>
                                    </form>
                                @endunless
                                @if ($hasCode)
                                    <form method="POST" action="{{ route('households.codes.destroy', $household->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-common.cashflow-submit>{{ __('Cabut kode') }}</x-common.cashflow-submit>
                                    </form>
                                @endif
                                <form method="dialog">
                                    <x-ui.button type="submit" variant="outline" size="lg" autofocus>{{ __('Tutup') }}</x-ui.button>
                                </form>
                            </div>
                        </div>
                    </dialog>
                </div>
            </x-slot:actions>
        @endif
    </x-common.page-breadcrumb>
    <x-common.component-card :title="$household->name">
        <div class="space-y-4 text-gray-900 dark:text-gray-100">
            @error('household')
                <p role="alert" class="text-error-700 dark:text-error-400">{{ $message }}</p>
            @enderror

            @if ($membership->role === \App\Models\HouseholdMembership::OWNER)
                @if ($partner)
                    <p>{{ __('Pasangan') }} · {{ $partner->user->name }}</p>
                    <form method="POST" action="{{ route('households.partner.destroy', [$household->id, $partner->id]) }}">
                        @csrf
                        @method('DELETE')
                        <x-common.cashflow-submit>{{ __('Cabut akses pasangan') }}</x-common.cashflow-submit>
                    </form>
                @else
                    <p>{{ __('Belum ada pasangan yang bergabung.') }}</p>
                @endif
            @else
                <form method="POST" action="{{ route('households.leave', $household->id) }}">
                    @csrf
                    @method('DELETE')
                    <x-common.cashflow-submit>{{ __('Keluar dari rumah tangga') }}</x-common.cashflow-submit>
                </form>
            @endif
        </div>
    </x-common.component-card>
@endsection
