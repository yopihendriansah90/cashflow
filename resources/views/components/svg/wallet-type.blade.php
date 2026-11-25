@props(['type', 'compact' => false])

<svg viewBox="0 0 28 28" fill="none" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"
    data-wallet-type-mark="{{ $type }}" aria-hidden="true"
    @class([
        'shrink-0 stroke-current',
        'size-5' => $compact,
        'size-7' => ! $compact,
        'text-wallet-cash' => $type === 'cash',
        'text-wallet-bank' => $type === 'bank',
        'text-wallet-ewallet' => $type === 'ewallet',
    ])>
    @switch($type)
        @case('bank')
            <path d="M3 10 14 4l11 6H3Zm3 12h16M4 24h20M8 10v8m6-8v8m6-8v8" />
            <circle cx="14" cy="7.2" r=".65" class="fill-current stroke-none" />
            @break
        @case('ewallet')
            <path d="M23 10V8a3 3 0 0 0-3-3H8a4 4 0 0 0 0 8h15m0-3v10a3 3 0 0 1-3 3H8a4 4 0 0 1-4-4V9" />
            <path d="m12 17 2.5 2.5L19 15" />
            @break
        @default
            <path d="M6 9V7a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v3" />
            <rect x="3" y="9" width="22" height="14" rx="3" />
            <circle cx="14" cy="16" r="3" />
            <path d="M7 16h1m12 0h1" />
    @endswitch
</svg>
