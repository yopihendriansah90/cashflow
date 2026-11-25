@php($iconAction = 'inline-flex size-8 shrink-0 items-center justify-center rounded-md transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current pointer-coarse:size-11')
<a href="{{ route('ledger.index', ['record' => $entry->id] + $context) }}"
    aria-label="{{ __('Ubah transaksi') }}" title="{{ __('Ubah transaksi') }}"
    class="{{ $iconAction }} bg-brand-50 text-brand-700 hover:bg-brand-100 dark:bg-brand-500/15 dark:text-brand-300 dark:hover:bg-brand-500/25">
    <x-svg.pencil aria-hidden="true" class="size-5" />
</a>
<form method="POST" action="{{ route('ledger.remove', ['record' => $entry->id] + $context) }}" data-confirm-action="{{ __('Lanjutkan perubahan ini?') }}">
    @csrf
    @method('DELETE')
    <button type="submit" aria-label="{{ __('Hapus transaksi') }}" title="{{ __('Hapus transaksi') }}"
        class="{{ $iconAction }} bg-error-50 text-error-700 hover:bg-error-100 dark:bg-error-500/15 dark:text-error-300 dark:hover:bg-error-500/25">
        <x-svg.trash-bin aria-hidden="true" class="size-5" />
    </button>
</form>
