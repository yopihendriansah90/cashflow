@extends('layouts.cashflow')

@section('content')
    @use('App\Support\Ledger')
    <x-common.page-breadcrumb :pageTitle="__('Beranda')">
        <x-slot:actions>
            <a class="inline-flex min-h-11 items-center rounded-lg bg-brand-600 px-4 font-semibold text-white dark:bg-brand-500"
                href="{{ route('ledger.index', ['section' => $hasWallet ? 'transactions' : 'wallets', 'baru' => 1]) }}">
                {{ __($hasWallet ? 'Catat transaksi' : 'Tambah dompet') }}
            </a>
        </x-slot:actions>
    </x-common.page-breadcrumb>
    <div class="space-y-5">
        <x-common.component-card :title="$membership->household->name">
            <p>{{ __('Saldo terkini') }} <strong>{{ Ledger::rupiah($total) }}</strong></p>
            @if ($negative)
                <p class="text-warning-700 dark:text-warning-300">{{ __('Ada dompet dengan saldo negatif.') }}</p>
            @endif
        </x-common.component-card>
        <x-common.component-card :title="__('Ringkasan bulanan')">
            <form method="GET" action="{{ route('dashboard') }}" class="mb-4 flex flex-wrap items-end gap-2">
                <div class="min-w-0">
                    <label for="dashboard-month" class="mb-1 block text-sm">{{ __('Bulan') }}</label>
                    <input id="dashboard-month" name="bulan" type="month" value="{{ $month }}" required
                        class="min-h-11 max-w-full rounded-lg border border-gray-300 bg-white px-2 dark:border-gray-700 dark:bg-gray-900" />
                </div>
                <x-ui.button type="submit" variant="outline">{{ __('Tampilkan') }}</x-ui.button>
            </form>
            <x-main.cashflow.month-summary :$summary />
            <div class="mt-5 min-w-0">
                <h3 class="font-semibold">{{ __('Arus kas harian') }}</h3>
                @if ($summary['income'] === '0' && $summary['expense'] === '0')
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Belum ada arus kas pada bulan ini.') }}</p>
                @else
                    <div aria-hidden="true" class="min-w-0" data-dashboard-chart="cashflow-daily"
                        data-config="{{ json_encode($summary['daily'], JSON_THROW_ON_ERROR) }}"></div>
                    <details class="mt-2 text-sm">
                        <summary class="min-h-11 cursor-pointer py-3">{{ __('Rincian harian') }}</summary>
                        <table class="w-full table-fixed text-start">
                            <thead><tr class="text-start">
                                <th scope="col" class="w-1/5 text-start">{{ __('Tanggal') }}</th>
                                <th scope="col" class="text-end">{{ __('Pemasukan') }}</th>
                                <th scope="col" class="text-end">{{ __('Pengeluaran') }}</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                @foreach ($summary['daily'] as $day => $amounts)
                                    <tr>
                                        <th scope="row" class="py-2 text-start font-normal">{{ $day }}</th>
                                        <td class="break-all text-end">{{ Ledger::rupiah($amounts['income']) }}</td>
                                        <td class="break-all text-end">{{ Ledger::rupiah($amounts['expense']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @endif
                <x-ui.button as="a" variant="outline" size="lg" class="mt-3" href="{{ route('reports.index', ['bulan' => $month]) }}">{{ __('Lihat laporan') }}</x-ui.button>
            </div>
        </x-common.component-card>
        <x-common.component-card :title="__('Transaksi terbaru')">
            <ul class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($recent as $row)
                    <li class="flex flex-wrap justify-between gap-2 break-words py-3">
                        <span>{{ Ledger::date($row->occurred_on) }} · {{ __(Ledger::TYPES[$row->type]) }} @if ($row->note) · {{ $row->note }} @endif</span>
                        <strong>{{ Ledger::rupiah($row->amount) }}</strong>
                    </li>
                @empty
                    <li>{{ __('Belum ada transaksi.') }}</li>
                @endforelse
            </ul>
        </x-common.component-card>
    </div>
@endsection
