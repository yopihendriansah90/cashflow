@props(['name', 'label', 'type' => 'text', 'value' => '', 'autocomplete' => null, 'optional' => false, 'fieldId' => null, 'rupiah' => false, 'signed' => false])
@php($fieldId ??= $name)

<div>
    <label for="{{ $fieldId }}" class="mb-1 block text-sm font-medium">{{ __($label) }}</label>
    <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" value="{{ is_scalar($value) ? $value : '' }}"
        @if ($rupiah)
            x-rupiah inputmode="{{ $signed ? 'text' : 'numeric' }}"
            pattern="{{ $signed ? '-?' : '' }}(?:[0-9]+|[0-9]{1,3}(?:\.[0-9]{3})+)"
            title="{{ __('Masukkan nominal rupiah, misalnya 1250000 atau 1.250.000.') }}"
        @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror
        @unless ($optional) required @endunless
        {{ $attributes->class('min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-gray-900 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white') }}>
    @error($name)
        <p id="{{ $fieldId }}-error" class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
    @enderror
</div>
