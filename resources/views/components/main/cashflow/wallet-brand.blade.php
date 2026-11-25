@props(['type', 'brandKey' => null, 'compact' => false])

@php
    $brand = \App\Support\WalletBrand::find($type, $brandKey);
    $logo = $brand ? \App\Support\WalletBrand::logo($brandKey) : null;
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center gap-1 rounded-lg px-2 text-xs font-semibold ring-1 ring-gray-950/10 dark:ring-white/20',
    'bg-gray-900 text-white' => $logo && ($brand['inverse'] ?? false),
    'bg-white text-gray-800' => ! $logo || ! ($brand['inverse'] ?? false),
    'min-h-8 min-w-10' => $compact,
    'min-h-10 min-w-12' => ! $compact,
]) }}>
    @if ($logo)
        <img src="{{ $logo }}" alt="{{ $brand['name'] }}" width="192" height="96"
            @class(['h-7 w-14' => $compact, 'h-9 w-18' => ! $compact])>
    @else
        <x-svg.wallet-type :$type :$compact />
        @if ($brand)<span>{{ $brand['name'] }}</span>@endif
    @endif
</span>
