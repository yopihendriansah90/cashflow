@props(['cancel' => false])

<div class="ms-auto flex w-full flex-wrap justify-end gap-2 sm:w-auto">
    @if ($cancel)
        <x-ui.button type="button" variant="outline" class="min-h-11" @click="close">{{ __('Batal') }}</x-ui.button>
    @endif
    <button type="submit" {{ $attributes->class([
        'min-h-11 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:opacity-60 dark:bg-brand-600 dark:hover:bg-brand-500',
        'w-full sm:w-auto' => ! $cancel,
    ]) }}>{{ $slot }}</button>
</div>
