@props(['value' => null])

<div class="space-y-2">
    <label class="block space-y-1">
        <span class="text-sm font-medium">{{ __('Logo dompet') }}</span>
        <select name="brand_key" x-model="brand" class="block min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 dark:border-gray-700 dark:bg-gray-900"
            @error('brand_key') aria-invalid="true" aria-describedby="wallet-brand-error" @enderror>
            <option value="">{{ __('Ikon bawaan aplikasi') }}</option>
            @foreach (config('wallet-brands') as $type => $brands)
                @foreach ($brands as $key => $entry)
                    <option value="{{ $key }}" :hidden="walletType !== @js($type)" :disabled="walletType !== @js($type)"
                        @selected($value === $key)>{{ $entry['name'] }}</option>
                @endforeach
            @endforeach
        </select>
    </label>
    @error('brand_key')
        <p id="wallet-brand-error" class="text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
    @enderror
    <div class="flex items-center gap-2">
        <span class="text-xs text-gray-600 dark:text-gray-300">{{ __('Pratinjau logo') }}</span>
        @foreach (\App\Support\Ledger::WALLETS as $type => $label)
            <template x-if="walletType === @js($type) && !brand">
                <x-main.cashflow.wallet-brand :$type compact />
            </template>
            @foreach (config("wallet-brands.$type", []) as $key => $entry)
                <template x-if="walletType === @js($type) && brand === @js($key)">
                    <x-main.cashflow.wallet-brand :$type :brand-key="$key" compact />
                </template>
            @endforeach
        @endforeach
    </div>
</div>
