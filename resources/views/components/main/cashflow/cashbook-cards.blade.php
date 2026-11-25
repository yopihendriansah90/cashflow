@use('App\Support\Ledger')

<div data-cashbook-cards class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-gray-50 p-4 text-sm font-medium dark:bg-gray-800">
        <span>{{ $carryLabel }}</span>
        <span class="break-all tabular-nums">{{ Ledger::rupiah($carry) }}
            @if (str_starts_with($carry, '-'))<span class="text-xs text-error-700 dark:text-error-300">{{ __('Saldo negatif') }}</span>@endif
        </span>
    </div>
    <ul class="grid items-stretch gap-3 md:grid-cols-2">
        @forelse ($rows as $row)
            @php($entry = $row['entry'])
            <li class="flex min-w-0 flex-col rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                    @if ($entry)<span>{{ __('No.') }} {{ $row['number'] }}</span>@endif
                    <time datetime="{{ $row['date'] }}">{{ Ledger::date($row['date']) }}</time>
                </div>
                @if ($entry)
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ __(Ledger::TYPES[$entry->type]) }}</h3>
                        <span @class([
                            'break-all text-lg font-semibold tabular-nums',
                            'text-success-700 dark:text-success-300' => $entry->type === 'income',
                            'text-error-700 dark:text-error-300' => $entry->type === 'expense',
                            'text-brand-700 dark:text-brand-300' => $entry->type === 'transfer',
                        ])>{{ Ledger::rupiah($entry->amount) }}</span>
                    </div>
                    <div class="mt-2 space-y-1 text-sm text-gray-600 [overflow-wrap:anywhere] dark:text-gray-300">
                        @if ($entry->category_id)<p>{{ $categoryNames[$entry->category_id] ?? '' }}</p>@endif
                        <p>{{ $walletNames[$entry->source_wallet_id] ?? '' }} @if ($entry->type === 'transfer') <span class="inline-block rtl:rotate-180">→</span> @endif {{ $walletNames[$entry->destination_wallet_id] ?? '' }}</p>
                        @if ($entry->fee_amount)<p>{{ __('Biaya Admin') }} {{ Ledger::rupiah($entry->fee_amount) }}</p>@endif
                        @if ($entry->type === 'transfer')
                            <p>{{ __('Masuk') }} {{ Ledger::rupiah($row['in']) }} · {{ __('Keluar') }} {{ Ledger::rupiah($row['out']) }}</p>
                        @endif
                        @if ($entry->note)<p class="whitespace-pre-wrap">{{ $entry->note }}</p>@endif
                    </div>
                @else
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('Saldo awal dompet') }}</h3>
                    <p class="mt-1 text-sm text-gray-600 [overflow-wrap:anywhere] dark:text-gray-300">{{ $row['wallet']->name }} · {{ Ledger::rupiah($row['wallet']->opening_balance) }}</p>
                @endif
                <div class="mt-auto pt-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <div class="min-w-0 text-sm">
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Saldo') }}</span>
                            <span class="break-all font-semibold tabular-nums">{{ Ledger::rupiah($row['balance']) }}</span>
                            @if (str_starts_with($row['balance'], '-'))<span class="block text-xs text-error-700 dark:text-error-300">{{ __('Saldo negatif') }}</span>@endif
                        </div>
                        @if ($entry)
                            <div class="ms-auto flex gap-1">@include('components.main.cashflow.cashbook-actions')</div>
                        @endif
                    </div>
                </div>
            </li>
        @empty
            <li class="rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-800 md:col-span-2">{{ __('Belum ada transaksi pada periode ini.') }}</li>
        @endforelse
    </ul>
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-gray-50 p-4 text-sm font-semibold dark:bg-gray-800">
        <span>{{ __('Saldo akhir bulan') }}</span>
        <span class="break-all tabular-nums">{{ Ledger::rupiah($cashbook['ending']) }}
            @if (str_starts_with($cashbook['ending'], '-'))<span class="text-xs text-error-700 dark:text-error-300">{{ __('Saldo negatif') }}</span>@endif
        </span>
    </div>
</div>
