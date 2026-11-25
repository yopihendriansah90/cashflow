<div data-quick-transaction-dialog>
    <x-common.cashflow-dialog :transaction="true" heading="Catat transaksi" :open="true">
        @include('components.main.cashflow.transaction-form', ['quick' => true, 'selected' => null, 'formAction' => route('ledger.save', 'transactions')])
    </x-common.cashflow-dialog>
</div>
