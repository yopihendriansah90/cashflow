<form method="POST" action="{{ $formAction }}" data-cashbook-transaction-form @if ($quick ?? false) data-quick-transaction data-save-error="{{ __('Transaksi gagal disimpan.') }}" @endif class="grid min-h-0 min-w-0 grid-rows-[minmax(0,1fr)_auto]"
    data-wallet-starts="{{ $wallets->pluck('opened_on', 'id')->toJson() }}"
    data-confirm-early="{{ __('Tanggal transaksi lebih awal dari tanggal mulai dompet. Jika dilanjutkan, tanggal mulai dan saldo awal dompet yang terdampak akan dimajukan ke tanggal transaksi. Saldo historis akan dihitung ulang. Jika batal, Anda bisa mengoreksi tanggal atau dompet. Lanjutkan?') }}">
    @csrf
    <input type="hidden" name="confirm_early_transaction" value="0">
    <div class="min-h-0 min-w-0 overflow-y-auto overscroll-y-contain px-4 py-4 sm:px-6">
        <x-main.cashflow.transaction-fields :$selected :$wallets :$categories :prefix="($quick ?? false) ? 'quick-' : ''" />
    </div>
    <div class="border-t border-gray-200 px-4 pt-3 pb-[max(.75rem,env(safe-area-inset-bottom))] dark:border-gray-800 sm:px-6">
        <x-common.cashflow-submit :cancel="true">{{ __('Simpan') }}</x-common.cashflow-submit>
    </div>
</form>
