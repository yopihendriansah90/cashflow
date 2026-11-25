@props(['summary'])

<dl class="grid gap-3 sm:grid-cols-3">
    @foreach (['income' => 'Pemasukan', 'expense' => 'Pengeluaran', 'net' => 'Arus bersih'] as $key => $label)
        <div class="min-w-0 break-words rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
            <dt>{{ __($label) }}</dt>
            <dd class="font-semibold">{{ \App\Support\Ledger::rupiah($summary[$key]) }}</dd>
        </div>
    @endforeach
</dl>
