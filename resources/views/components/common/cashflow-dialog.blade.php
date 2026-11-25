@props(['heading', 'open' => false, 'trigger' => null, 'transaction' => false])

<div x-data="cashflowDialog(@js($open))">
    @if ($trigger)
        <x-ui.button type="button" x-ref="trigger" @click="show">{{ __($trigger) }}</x-ui.button>
    @endif
    <dialog x-ref="dialog" data-discard="{{ __('Tutup formulir dengan perubahan yang belum disimpan?') }}"
        @cancel.prevent="close" @close="restore" @input="dirty = true" @submit="submit" :aria-busy="pending"
        @class([
            'cashflow-dialog fixed inset-x-0 bottom-0 top-auto m-0 max-h-[90dvh] w-full max-w-none rounded-t-2xl bg-white text-gray-900 backdrop:bg-gray-950/60 dark:bg-gray-900 dark:text-gray-100 sm:inset-0 sm:m-auto sm:w-[min(36rem,95vw)] sm:rounded-2xl',
            'cashflow-transaction-sheet overflow-hidden' => $transaction,
            'overflow-auto' => ! $transaction,
        ])
        @if ($transaction) data-transaction-sheet @endif
        aria-label="{{ __($heading) }}">
        <div @class(['grid max-h-[inherit] min-h-0 grid-rows-[auto_minmax(0,1fr)_auto]' => $transaction, 'space-y-4 p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:p-6' => ! $transaction])>
            <h2 @class(['text-lg font-semibold', 'border-b border-gray-200 px-4 py-3 dark:border-gray-800 sm:px-6' => $transaction]) tabindex="-1" autofocus>{{ __($heading) }}</h2>
            {{ $slot }}
            <p role="status" x-show="pending" x-cloak @class(['px-4 pb-2 sm:px-6' => $transaction])>{{ __('Menyimpan…') }}</p>
        </div>
    </dialog>
</div>
