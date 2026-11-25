@props(['cashbook', 'period', 'wallets', 'walletId', 'categoryNames', 'walletNames'])
<section class="mt-5" aria-labelledby="cashbook-heading" x-data="cashbookFilters" data-cashbook-mode="" :data-cashbook-mode="mode"
    data-loading="{{ __('Memuat transaksi?') }}" data-error="{{ __('Transaksi gagal dimuat. Coba pilih filter lagi.') }}">
    <div class="flex items-center justify-between gap-3">
        <h2 id="cashbook-heading" class="text-lg font-semibold">{{ __('Buku kas bulanan') }}</h2>
        <div class="shrink-0">{{ $actions }}</div>
    </div>
    <form method="GET" action="{{ route('ledger.index', 'transactions') }}" data-cashbook-filters x-ref="filters" @submit.prevent="filter()" class="my-4 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-auto sm:min-w-64">
            <x-common.cashflow-field name="q" label="Cari transaksi" type="search" :value="$cashbook['search']" maxlength="120"
                :placeholder="__('Catatan, kategori, atau dompet')" @input="if (!$event.isComposing) search()" @compositionend="search()" optional />
        </div>
        <x-common.cashflow-field name="bulan" label="Bulan" type="month" :value="$period->month" min="1000-01" max="9999-12" @change="filter()" required />
        <x-common.cashflow-select name="dompet" label="Dompet" placeholder="Semua dompet" :value="$walletId" :chevron="true" @change="filter()"
            :options="$wallets->mapWithKeys(fn ($wallet) => [$wallet->id => $wallet->name.($wallet->archived_at ? ' ('.__('Diarsipkan').')' : '')])" />
        <button type="submit" class="sr-only focus:not-sr-only">{{ __('Cari transaksi') }}</button>
        <div x-cloak role="group" aria-label="{{ __('Tampilan') }}" class="ms-auto flex shrink-0 gap-1 rounded-xl bg-gray-50 ring-1 ring-gray-200 ring-inset dark:bg-gray-900 dark:ring-gray-800">
            @foreach (['cards' => 'Kartu', 'table' => 'Buku Kas'] as $value => $label)
                <button type="button" @click="chooseMode(@js($value))" :aria-pressed="mode === @js($value)"
                    class="min-h-11 rounded-xl px-2.5 text-sm font-medium whitespace-nowrap text-gray-600 hover:text-brand-700 focus-visible:outline-2 focus-visible:outline-brand-500 aria-pressed:bg-white aria-pressed:text-brand-700 aria-pressed:shadow-theme-xs sm:px-4 dark:text-gray-300 dark:hover:text-brand-300 dark:aria-pressed:bg-gray-800 dark:aria-pressed:text-brand-300">
                    {{ __($label) }}
                </button>
            @endforeach
        </div>
    </form>

    <p role="status" x-text="status" class="my-2 text-sm"></p>
    <div x-ref="results" tabindex="-1" :aria-busy="loading" @click="paginate($event)">
        @include('components.main.cashflow.cashbook-results')
    </div>
</section>
