@props(['wallet', 'balance' => null, 'holder' => null])

@php
    use App\Support\Ledger;
    $brandKey = $wallet->brand_key ?? null;
    $actionClass = 'inline-flex size-11 items-center justify-center rounded-lg text-current ring-1 ring-inset ring-current/40 hover:ring-current focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current';
@endphp

<div data-wallet-brand="{{ $brandKey }}" data-wallet-type="{{ $wallet->type }}" class="wallet-card grid min-h-48 min-w-0 content-between gap-5 rounded-2xl p-4 shadow-theme-md dark:ring-1 dark:ring-white/15 sm:p-5">
    <div class="flex min-w-0 flex-wrap items-center justify-between gap-2">
        <x-main.cashflow.wallet-brand :type="$wallet->type" :brand-key="$brandKey" />
        <p class="text-xs font-medium">{{ __(Ledger::WALLETS[$wallet->type]) }}</p>
    </div>
    <div class="min-w-0">
        <p class="text-xs">{{ __('Saldo terkini') }}</p>
        <p class="mt-1 text-2xl font-semibold tabular-nums [overflow-wrap:anywhere]">{{ $balance === null ? '—' : Ledger::rupiah($balance) }}</p>
        @if ($balance !== null && str_starts_with((string) $balance, '-'))
            <p class="mt-1 w-fit rounded-md bg-warning-100 px-2 py-1 text-xs font-medium text-warning-900">{{ __('Saldo negatif') }}</p>
        @endif
    </div>
    <div class="flex min-w-0 items-start justify-between gap-2 border-t border-current/25 pt-3">
        <div class="min-w-0">
            <p class="font-semibold [overflow-wrap:anywhere]">{{ $wallet->name }}</p>
            <p class="mt-1 text-xs [overflow-wrap:anywhere]">
                {{ $holder?->user->name ?? __('Bersama') }} @if ($holder?->ended_at) ({{ __('Keluar') }}) @endif · {{ Ledger::date($wallet->opened_on) }}
            </p>
            @if ($wallet->archived_at)<p class="mt-1 text-xs font-medium">{{ __('Diarsipkan') }}</p>@endif
        </div>
        <div class="flex shrink-0 gap-2">
            <a href="{{ route('ledger.index', ['wallets', $wallet->id]) }}"
                class="{{ $actionClass }}" aria-label="{{ __('Ubah') }}: {{ $wallet->name }}" title="{{ __('Ubah') }}">
                <x-svg.pencil class="size-5" aria-hidden="true" />
            </a>
            @unless ($wallet->archived_at)
                <form method="POST" action="{{ route('ledger.remove', ['wallets', $wallet->id]) }}"
                    data-confirm-action="{{ __('Lanjutkan perubahan ini?') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="{{ $actionClass }}"
                        aria-label="{{ __('Arsipkan') }}: {{ $wallet->name }}" title="{{ __('Arsipkan') }}">
                        <x-svg.archive class="size-5" aria-hidden="true" />
                    </button>
                </form>
            @endunless
        </div>
    </div>
</div>
