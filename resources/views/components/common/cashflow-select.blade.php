@props(['name', 'label', 'options', 'value' => null, 'placeholder' => null, 'chevron' => false])

<label class="block space-y-1">
    <span class="text-sm font-medium">{{ __($label) }}</span>
    <span class="relative block">
        <select name="{{ $name }}" {{ $attributes->class([
            'block min-h-11 w-full border bg-white dark:bg-gray-900',
            'rounded-lg border-gray-300 px-3 dark:border-gray-700' => ! $chevron,
            'cursor-pointer appearance-none rounded-xl border-gray-200 ps-4 pe-11 text-sm font-medium text-gray-800 shadow-theme-xs transition-colors hover:border-brand-300 focus-visible:border-brand-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:border-gray-700 dark:text-gray-100 dark:hover:border-brand-400' => $chevron,
        ]) }}>
            @if ($placeholder !== null)
                <option value="">{{ __($placeholder) }}</option>
            @endif
            @foreach ($options as $key => $caption)
                <option value="{{ $key }}" @selected(is_scalar($value) && (string) $value === (string) $key)>{{ $caption }}</option>
            @endforeach
        </select>
        @if ($chevron)
            <span aria-hidden="true" class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-gray-500 dark:text-gray-400">
                <x-svg.chevron-down class="size-4" />
            </span>
        @endif
    </span>
    @error($name)
        <span class="text-sm text-error-600 dark:text-error-400">{{ $message }}</span>
    @enderror
</label>
