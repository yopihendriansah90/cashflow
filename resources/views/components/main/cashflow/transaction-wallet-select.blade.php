@props(['wallets', 'name', 'label', 'value' => null])

@php
    $value = is_scalar($value) ? (string) $value : '';
    $options = $wallets->mapWithKeys(function ($wallet) {
        $brand = \App\Support\WalletBrand::find($wallet->type, $wallet->brand_key ?? null)['name'] ?? null;
        $caption = $brand && ! str_contains(mb_strtolower($wallet->name), mb_strtolower($brand))
            ? $wallet->name.' · '.$brand
            : $wallet->name;

        return [$wallet->id => $caption];
    });
@endphp

<div class="flex items-end gap-2" x-data="{ wallet: @js($value) }">
    <div class="min-w-0 flex-1">
        <x-common.cashflow-select :$name :$label :$options :$value placeholder="Pilih dompet" x-model="wallet" />
    </div>
    @foreach ($wallets as $entry)
        <template x-if="wallet === @js((string) $entry->id)">
            <x-main.cashflow.wallet-brand :type="$entry->type" :brand-key="$entry->brand_key ?? null" compact class="self-start mt-7 max-w-28" />
        </template>
    @endforeach
</div>
