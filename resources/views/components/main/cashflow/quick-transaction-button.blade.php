<div x-data="quickTransaction">
    <button type="button" @click="open($event.currentTarget)" :aria-busy="loading"
        data-url="{{ route('ledger.quick-transaction') }}"
        data-no-wallet="{{ __('Tambahkan dompet sebelum mencatat transaksi.') }}"
        data-add-wallet="{{ __('Tambah dompet') }}"
        data-load-error="{{ __('Formulir transaksi gagal dimuat.') }}"
        aria-label="{{ __('Catat transaksi') }}" title="{{ __('Catat transaksi') }}"
        class="fixed end-5 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-9 inline-flex size-14 items-center justify-center rounded-full bg-brand-600 text-white shadow-theme-lg transition-colors hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 xl:end-6 xl:bottom-6 dark:bg-brand-600 dark:hover:bg-brand-500">
        <x-svg.plus aria-hidden="true" class="size-6" />
    </button>
    <div x-ref="form"></div>
</div>
