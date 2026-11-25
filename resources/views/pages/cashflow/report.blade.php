@extends('layouts.cashflow')

@section('content')
    @use('App\Support\Ledger')
    <x-common.page-breadcrumb :pageTitle="__('Laporan')">
        <x-slot:actions>
            <x-ui.button as="a" variant="outline" size="lg" href="{{ route('reports.export', ['bulan' => $report->month]) }}">{{ __('Unduh CSV') }}</x-ui.button>
        </x-slot:actions>
    </x-common.page-breadcrumb>
    <x-common.component-card :title="__('Ringkasan bulanan')">
        <form method="GET" action="{{ route('reports.index') }}" class="mb-5 flex flex-wrap items-end gap-3">
            <x-common.cashflow-field name="bulan" label="Bulan" type="month" :value="$report->month" min="1000-01" max="9999-12" />
            <x-common.cashflow-submit>{{ __('Tampilkan') }}</x-common.cashflow-submit>
        </form>
        <x-main.cashflow.month-summary :$summary />
        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            @foreach ([
                'Pengeluaran per kategori' => $summary['breakdown'],
                'Saldo akhir dompet' => $summary['ending'],
                'Zakat, infak, dan sedekah' => collect($summary['giving'])->map(fn ($amount, $code) => ['name' => __(ucfirst($code)), 'amount' => $amount]),
            ] as $title => $items)
                <section class="min-w-0">
                    <h2 class="mb-2 font-semibold">{{ __($title) }}</h2>
                    <dl>
                        @forelse ($items as $item)
                            <div class="flex flex-wrap justify-between gap-2 break-words border-b border-gray-200 py-2 dark:border-gray-800">
                                <dt class="min-w-0">{{ $item['name'] }}</dt>
                                <dd class="min-w-0 font-medium">{{ Ledger::rupiah($item['amount']) }}</dd>
                            </div>
                        @empty
                            <div>{{ __('Belum ada data.') }}</div>
                        @endforelse
                    </dl>
                </section>
            @endforeach
        </div>
    </x-common.component-card>
@endsection
