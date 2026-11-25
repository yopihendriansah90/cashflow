@props(['selected' => null, 'wallets', 'categories', 'prefix' => ''])

@php
    $walletOptions = fn ($field) => $wallets->filter(fn ($row) => ! $row->archived_at || $row->id === ($selected?->$field));
    $type = old('type', $selected?->type ?? 'expense');
    $amount = old('amount', $selected?->amount);
    $fee = old('fee_amount', $selected?->fee_amount);
    $options = $categories->filter(fn ($row) => ! $row->archived_at || $row->id === $selected?->category_id)->groupBy('type');
@endphp
<div class="space-y-3" x-data="{ type: @js(is_string($type) ? $type : 'expense'), amount: @js(is_scalar($amount) ? (string) $amount : ''), fee: @js(is_scalar($fee) ? (string) $fee : '') }">
    <fieldset class="min-w-0 sm:hidden">
        <legend class="mb-1 text-sm font-medium">{{ __('Jenis') }}</legend>
        <div class="grid grid-cols-3 gap-1 rounded-xl bg-gray-100 p-1 dark:bg-gray-800">
            @foreach (\App\Support\Ledger::TYPES as $value => $label)
                <label class="relative min-w-0">
                    <input type="radio" name="type_choice" value="{{ $value }}" x-model="type" @checked($type === $value) class="peer sr-only">
                    <span class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg px-1 text-center text-xs font-medium text-gray-700 [overflow-wrap:anywhere] peer-checked:bg-white peer-checked:text-brand-700 peer-checked:shadow-theme-xs peer-focus-visible:outline-2 peer-focus-visible:outline-brand-500 dark:text-gray-200 dark:peer-checked:bg-gray-900 dark:peer-checked:text-brand-300">{{ __($label) }}</span>
                </label>
            @endforeach
        </div>
        @error('type') <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p> @enderror
    </fieldset>
    <div class="hidden sm:block">
        <x-common.cashflow-select name="type" label="Jenis" :options="array_map(fn ($label) => __($label), \App\Support\Ledger::TYPES)" :value="$type" x-model="type" />
    </div>
    <x-common.cashflow-field name="amount" :field-id="$prefix.'amount'" label="Nominal diterima atau dicatat (Rp)" :value="old('amount', $selected?->amount)" :rupiah="true" x-model="amount" required />
    <x-common.cashflow-field type="date" name="occurred_on" :field-id="$prefix.'occurred_on'" label="Tanggal" :value="old('occurred_on', $selected?->occurred_on ?? \App\Support\Ledger::today())" class="min-w-0 max-w-full" required />

    <fieldset x-show="type !== 'income'" :disabled="type === 'income'">
        <x-main.cashflow.transaction-wallet-select name="source_wallet_id" label="Dompet asal" :wallets="$walletOptions('source_wallet_id')" :value="old('source_wallet_id', $selected?->source_wallet_id)" />
    </fieldset>
    <fieldset x-show="type !== 'expense'" :disabled="type === 'expense'">
        <x-main.cashflow.transaction-wallet-select name="destination_wallet_id" label="Dompet tujuan" :wallets="$walletOptions('destination_wallet_id')" :value="old('destination_wallet_id', $selected?->destination_wallet_id)" />
    </fieldset>
    @foreach (['income', 'expense'] as $kind)
        <fieldset x-show="type === @js($kind)" :disabled="type !== @js($kind)">
            <x-common.cashflow-select name="category_id" label="Kategori" :options="($options[$kind] ?? collect())->pluck('name', 'id')" :value="old('category_id', $selected?->category_id)" placeholder="Pilih kategori" />
        </fieldset>
    @endforeach
    <fieldset x-show="type === 'transfer'" :disabled="type !== 'transfer'" class="space-y-2">
        <x-common.cashflow-field name="fee_amount" :field-id="$prefix.'fee_amount'" label="Biaya transfer (Rp)" :value="old('fee_amount', $selected?->fee_amount)" :rupiah="true" x-model="fee" :optional="true" />
        <p>{{ __('Total potongan') }} <output x-text="$transferTotal(amount, fee)"></output></p>
    </fieldset>
    <x-common.cashflow-field name="note" :field-id="$prefix.'note'" label="Catatan (opsional)" :value="old('note', $selected?->note)" maxlength="500" :optional="true" />
</div>
