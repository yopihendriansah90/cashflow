@extends('layouts.cashflow')

@section('content')
    @php
        use App\Support\Ledger;
        $heading = ['wallets' => 'Dompet', 'categories' => 'Kategori', 'transactions' => 'Transaksi'][$section];
        $action = ['wallets' => 'Tambah dompet', 'categories' => 'Tambah kategori', 'transactions' => 'Catat transaksi'][$section];
        $context = $period ? ['bulan' => $period->month, 'dompet' => $walletId, 'q' => $cashbook['search'] !== '' ? $cashbook['search'] : null] : [];
        $formAction = route('ledger.save', ['section' => $section, 'record' => $selected?->id] + $context);
        $correction = old('_dialog') === 'correction';
        $walletNames = $wallets->pluck('name', 'id');
        $categoryNames = $categories->pluck('name', 'id');
    @endphp

    <x-common.page-breadcrumb :pageTitle="__($heading)">
        @if ($section !== 'transactions')
            <x-slot:actions>
                <x-common.cashflow-dialog :heading="$selected ? 'Ubah data' : $action" :trigger="$selected ? 'Ubah data' : $action" :open="! $correction && ($selected || $errors->any() || request()->boolean('baru'))">
                    @php
                        $walletType = old('type', $selected?->type ?? 'cash');
                        $walletType = is_string($walletType) ? $walletType : 'cash';
                        $brandKey = old('brand_key', $selected->brand_key ?? null);
                        $brandKey = is_string($brandKey) && \App\Support\WalletBrand::find($walletType, $brandKey) ? $brandKey : '';
                    @endphp
                    <form method="POST" action="{{ $formAction }}" class="space-y-3"
                        @if ($section === 'wallets') x-data="{ walletType: @js($walletType), brand: @js($brandKey) }" @endif>
                        @csrf
                        <x-common.cashflow-field name="name" label="Nama" :value="old('name', $selected?->name)" maxlength="120" required />
                        @if ($section === 'wallets' || ! $selected)
                            @php($labels = $section === 'wallets' ? Ledger::WALLETS : array_intersect_key(Ledger::TYPES, array_flip(['income', 'expense'])))
                            @if ($section === 'wallets')
                                <x-common.cashflow-select name="type" label="Jenis" :options="array_map(fn ($label) => __($label), $labels)" :value="$walletType" x-model="walletType" @change="brand = ''" />
                                <x-main.cashflow.wallet-brand-picker :value="$brandKey" />
                            @else
                                <x-common.cashflow-select name="type" label="Jenis" :options="array_map(fn ($label) => __($label), $labels)" :value="old('type')" />
                            @endif
                        @endif
                        @if ($section === 'wallets')
                            <x-common.cashflow-field type="date" name="opened_on" label="Tanggal mulai" :value="old('opened_on', $selected?->opened_on ?? Ledger::today())" required />
                            @unless ($selected)
                                <x-common.cashflow-field name="opening_balance" label="Saldo awal (Rp)" :value="old('opening_balance', 0)" :rupiah="true" :signed="true" required />
                            @endunless
                            @php($holderOptions = $holders->filter(fn ($holder) => ! $holder->ended_at || $holder->id === $selected?->holder_membership_id)->map(fn ($holder) => $holder->user->name.($holder->ended_at ? ' ('.__('Keluar').')' : '')))
                            <x-common.cashflow-select name="holder_membership_id" label="Pemegang" :options="$holderOptions" :value="old('holder_membership_id', $selected?->holder_membership_id)" placeholder="Bersama" />
                        @endif
                        <x-common.cashflow-submit :cancel="true">{{ __('Simpan') }}</x-common.cashflow-submit>
                    </form>
                </x-common.cashflow-dialog>
            </x-slot:actions>
        @endif
    </x-common.page-breadcrumb>
    <x-common.component-card :title="__($heading)">
        @if ($section === 'wallets' && $selected)
            <div class="flex justify-end">
                <x-common.cashflow-dialog heading="Koreksi saldo awal" trigger="Koreksi saldo awal" :open="$correction && $errors->any()">
                    <form method="POST" action="{{ route('ledger.correct', $selected->id) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="_dialog" value="correction">
                        <x-common.cashflow-field name="opening_balance" label="Saldo awal (Rp)" :value="old('opening_balance', $selected->opening_balance)" :rupiah="true" :signed="true" required />
                        <x-common.cashflow-submit :cancel="true">{{ __('Simpan') }}</x-common.cashflow-submit>
                    </form>
                </x-common.cashflow-dialog>
            </div>
        @endif
        @if ($section === 'transactions')
            <x-main.cashflow.cashbook :$cashbook :$period :$wallets :$walletId :$categoryNames :$walletNames>
                <x-slot:actions>
                    @if ($selected || $wallets->contains(fn ($wallet) => ! $wallet->archived_at))
                    <x-common.cashflow-dialog :transaction="true" :heading="$selected ? 'Ubah data' : $action" :trigger="$selected ? 'Ubah data' : $action" :open="! $correction && ($selected || $errors->any() || request()->boolean('baru'))">
                        @include('components.main.cashflow.transaction-form')
                    </x-common.cashflow-dialog>
                    @else
                    <x-ui.button as="a" variant="outline" size="lg" href="{{ route('ledger.index', ['section' => 'wallets', 'baru' => 1]) }}">{{ __('Tambah dompet') }}</x-ui.button>
                    @endif
                </x-slot:actions>
            </x-main.cashflow.cashbook>
        @else
            <ul class="mt-4 grid gap-3 md:grid-cols-2">
                @forelse (compact('wallets', 'categories')[$section] as $row)
                    <li @class(['min-w-0 break-words', 'space-y-1 rounded-lg border border-gray-200 p-3 dark:border-gray-800' => $section === 'categories'])>
                        @if ($section === 'wallets')
                            <x-main.cashflow.wallet-card :wallet="$row" :balance="$balances[$row->id] ?? null" :holder="$holders[$row->holder_membership_id] ?? null" />
                        @elseif ($section === 'categories')
                            <p class="font-semibold">{{ $row->name }} · {{ __(Ledger::TYPES[$row->type]) }}</p>
                        @endif
                        @if ($section === 'categories' && ($row->archived_at ?? null))
                            <p>{{ __('Diarsipkan') }}</p>
                        @endif
                        @if ($section === 'categories' && $row->household_id)
                            <div class="flex flex-wrap gap-4">
                                <x-ui.button as="a" variant="outline" size="lg" href="{{ route('ledger.index', [$section, $row->id]) }}">{{ __('Ubah') }}</x-ui.button>
                                @unless ($row->archived_at ?? null)
                                    <form method="POST" action="{{ route('ledger.remove', [$section, $row->id]) }}" data-confirm-action="{{ __('Lanjutkan perubahan ini?') }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" variant="outline" size="lg">{{ __('Arsipkan') }}</x-ui.button>
                                    </form>
                                @endunless
                            </div>
                        @endif
                    </li>
                @empty
                    <li>{{ __('Belum ada data.') }}</li>
                @endforelse
            </ul>
        @endif
    </x-common.component-card>

    @if ($section === 'wallets' && $selected)
        <x-common.component-card :title="__('Riwayat koreksi saldo awal')" class="mt-6">
            <ul class="space-y-2">
                @forelse ($corrections as $revision)
                    <li class="break-words">
                        {{ Ledger::rupiah($revision->before_amount) }} → {{ Ledger::rupiah($revision->after_amount) }} ·
                        {{ $holders[$revision->actor_membership_id]->user->name }} · {{ Ledger::auditTime($revision->created_at) }}
                    </li>
                @empty
                    <li>{{ __('Belum ada perubahan.') }}</li>
                @endforelse
            </ul>
            <x-main.cashflow.pagination :paginator="$corrections" label="Halaman koreksi saldo" />
        </x-common.component-card>
    @endif
@endsection
