@use('App\Support\Ledger')

@php
    $rows = $cashbook['rows'];
    $context = ['section' => 'transactions', 'bulan' => $period->month, 'dompet' => $walletId];
    $cell = 'px-4 py-3';
    $carry = $rows->onFirstPage() ? $cashbook['opening'] : $cashbook['carry'];
    $carryLabel = $rows->onFirstPage() ? __('Saldo bulan lalu') : __('Saldo dari halaman sebelumnya');
@endphp

<div data-cashbook-results class="min-w-0">
    <div data-cashbook-table class="min-w-0">
        <p id="cashbook-scroll-hint" class="mb-2 text-xs text-gray-600 dark:text-gray-400 md:hidden">{{ __('Geser tabel untuk melihat semua kolom.') }}</p>
        <div data-cashbook-scroll tabindex="0" role="region" aria-labelledby="cashbook-heading" aria-describedby="cashbook-scroll-hint" class="min-w-0 max-w-full overflow-x-auto overscroll-x-contain rounded-lg border border-gray-200 dark:border-gray-800">
            <table role="table" aria-labelledby="cashbook-heading" class="w-full min-w-[44rem] text-start text-sm">
                <thead role="rowgroup">
                    <tr role="row" class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                        @foreach (['No.', 'Tanggal', 'Keterangan', 'Masuk', 'Keluar', 'Saldo', 'Aksi'] as $label)
                            <th role="columnheader" scope="col"
                                @class([
                                    'border-e border-gray-200 px-4 py-3 text-xs font-semibold text-gray-600 last:border-e-0 dark:border-gray-700 dark:text-gray-300',
                                    'text-end' => in_array($label, ['Masuk', 'Keluar', 'Saldo'], true),
                                    'text-center' => in_array($label, ['No.', 'Aksi'], true),
                                    'text-start' => in_array($label, ['Tanggal', 'Keterangan'], true),
                                ])>{{ __($label) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    <tr role="row" class="bg-gray-50 py-2 font-semibold dark:bg-gray-800">
                        <td role="cell" colspan="5" class="{{ $cell }}">{{ $carryLabel }}</td>
                        <td role="cell" class="{{ $cell }} whitespace-nowrap tabular-nums text-end">{{ Ledger::rupiah($carry) }} @if (str_starts_with($carry, '-'))<span class="block text-xs text-error-700 dark:text-error-300">{{ __('Saldo negatif') }}</span>@endif</td>
                        <td role="cell"></td>
                    </tr>
                    @forelse ($rows as $row)
                        @php($entry = $row['entry'])
                        <tr role="row" class="border-t border-gray-200 py-3 dark:border-gray-800 align-top [&>td:not(:last-child)]:border-e [&>td]:border-gray-200 dark:[&>td]:border-gray-800">
                            <td role="cell" class="{{ $cell }} text-center tabular-nums">{{ $row['number'] }}</td>
                            <td role="cell" class="{{ $cell }} whitespace-nowrap">{{ Ledger::date($row['date']) }}</td>
                            <td role="cell" class="{{ $cell }} min-w-44 break-words [overflow-wrap:anywhere]">
                                @if ($entry)
                                    <p class="font-semibold">{{ __(Ledger::TYPES[$entry->type]) }} @if ($entry->type === 'transfer') · <span class="whitespace-nowrap">{{ Ledger::rupiah($entry->amount) }}</span> @endif</p>
                                    @if ($entry->category_id)<p>{{ $categoryNames[$entry->category_id] ?? '' }}</p>@endif
                                    <p>{{ $walletNames[$entry->source_wallet_id] ?? '' }} @if ($entry->type === 'transfer') <span class="inline-block rtl:rotate-180">→</span> @endif {{ $walletNames[$entry->destination_wallet_id] ?? '' }}</p>
                                    @if ($entry->fee_amount)<p>{{ __('Biaya Admin') }} <span class="whitespace-nowrap">{{ Ledger::rupiah($entry->fee_amount) }}</span></p>@endif
                                    @if ($entry->note)<p>{{ $entry->note }}</p>@endif
                                @else
                                    <p class="font-semibold">{{ __('Saldo awal dompet') }}</p>
                                    <p>{{ $row['wallet']->name }} · <span class="whitespace-nowrap">{{ Ledger::rupiah($row['wallet']->opening_balance) }}</span></p>
                                @endif
                            </td>
                            @foreach (['in' => 'Masuk', 'out' => 'Keluar', 'balance' => 'Saldo'] as $key => $label)
                                <td role="cell" class="{{ $cell }} whitespace-nowrap tabular-nums text-end">
                                    {{ $key === 'balance' || ($entry && $row[$key] !== '0') ? Ledger::rupiah($row[$key]) : '—' }}
                                    @if ($key === 'balance' && str_starts_with($row[$key], '-'))<span class="block text-xs text-error-700 dark:text-error-300">{{ __('Saldo negatif') }}</span>@endif
                                </td>
                            @endforeach
                            <td role="cell" class="px-2 py-2">
                                @if ($entry)
                                    <div class="flex min-w-max justify-center gap-1">
                                        @include('components.main.cashflow.cashbook-actions')
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr role="row"><td role="cell" colspan="7" class="px-3 py-4">{{ __('Belum ada transaksi pada periode ini.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot role="rowgroup">
                    <tr role="row" class="border-t border-gray-200 bg-gray-50 py-2 font-semibold dark:border-gray-800 dark:bg-gray-800">
                        <td role="cell" colspan="5" class="{{ $cell }}">{{ __('Saldo akhir bulan') }}</td>
                        <td role="cell" class="{{ $cell }} whitespace-nowrap tabular-nums text-end">{{ Ledger::rupiah($cashbook['ending']) }} @if (str_starts_with($cashbook['ending'], '-'))<span class="block text-xs text-error-700 dark:text-error-300">{{ __('Saldo negatif') }}</span>@endif</td>
                        <td role="cell"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @include('components.main.cashflow.cashbook-cards')
    <x-main.cashflow.pagination :paginator="$rows" label="Halaman transaksi" />
</div>
