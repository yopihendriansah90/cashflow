@extends('layouts.app')

@section('document-language', 'id')
@section('desktop-sidebar-only', '1')

@section('app-header')
    <header class="flex flex-wrap items-center justify-between gap-x-4 border-b border-gray-200 bg-white px-4 py-2 text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100 md:px-6">
        <p class="min-w-0 py-2 text-sm"><x-common.cashflow-brand /></p>
        <div class="flex flex-wrap items-center gap-x-3">
            <x-ui.button type="button" variant="ghost" size="icon-lg" aria-label="{{ __('Ubah tema') }}" @click="$store.theme.toggle()">
                <x-svg.sun x-show="$store.theme.resolvedTheme === 'dark'" />
                <x-svg.moon x-show="$store.theme.resolvedTheme !== 'dark'" />
            </x-ui.button>
            <details class="relative" x-data @keydown.escape.stop="$el.open = false; $refs.account.focus()" @click.outside="$el.open = false">
                <summary x-ref="account" class="flex min-h-11 max-w-40 cursor-pointer list-none items-center gap-2 rounded-lg px-2 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800">
                    <span class="truncate">{{ auth()->user()->name }}</span>
                    <span aria-hidden="true" class="size-2 shrink-0 rotate-45 border-e border-b border-current"></span>
                </summary>
                <form method="POST" action="{{ route('logout') }}"
                    class="absolute end-0 top-full z-99 mt-1 w-48 rounded-lg border border-gray-200 bg-white p-1 shadow-theme-md dark:border-gray-800 dark:bg-gray-900">
                    @csrf
                    <button type="submit" class="flex min-h-11 w-full items-center rounded px-3 text-start text-sm hover:bg-gray-100 dark:hover:bg-gray-800">
                        {{ __('Keluar dari akun') }}
                    </button>
                </form>
            </details>
        </div>
    </header>
@endsection

@section('app-footer')
    @php
        $items = \App\Helpers\MenuHelper::cashflowItems($membership);
        $moreItems = array_slice($items, 4);
        $isCurrent = fn ($item) => $item['path'] === '/'
            ? request()->routeIs('dashboard')
            : request()->is(ltrim($item['path'], '/'), ltrim($item['path'], '/').'/*');
        $moreActive = collect($moreItems)->contains($isCurrent);
    @endphp
    <nav aria-label="{{ __('Navigasi utama') }}"
        class="fixed inset-x-0 bottom-0 z-10 grid h-[calc(4rem+env(safe-area-inset-bottom))] grid-cols-5 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] text-xs dark:border-gray-800 dark:bg-gray-900 xl:hidden">
        @foreach (array_slice($items, 0, 4) as $item)
            <a href="{{ $item['path'] }}" @if ($isCurrent($item)) aria-current="page" @endif
                class="flex min-h-11 flex-col items-center justify-center gap-0.5 py-1.5 text-center text-gray-700 aria-[current=page]:font-semibold aria-[current=page]:text-brand-700 dark:text-gray-300 dark:aria-[current=page]:text-brand-300">
                <span aria-hidden="true" class="[&>svg]:size-5">{!! \App\Helpers\MenuHelper::getIconSvg($item['icon']) !!}</span>
                <span>{{ __($item['name']) }}</span>
            </a>
        @endforeach
        <details class="relative" x-data @keydown.escape.stop="$el.open = false; $refs.more.focus()" @click.outside="$el.open = false">
            <summary x-ref="more" @class([
                'flex h-full min-h-11 cursor-pointer list-none flex-col items-center justify-center gap-0.5 py-1.5',
                'font-semibold text-brand-700 dark:text-brand-300' => $moreActive,
                'text-gray-700 dark:text-gray-300' => ! $moreActive,
            ])>
                <x-svg.horizontal-dots class="size-5" aria-hidden="true" />
                <span>{{ __('Lainnya') }}</span>
            </summary>
            <ul class="absolute end-2 bottom-full w-48 rounded-lg border border-gray-200 bg-white p-2 shadow-theme-md dark:border-gray-800 dark:bg-gray-900">
                @foreach ($moreItems as $item)
                    <li><a href="{{ $item['path'] }}" @if ($isCurrent($item)) aria-current="page" @endif
                        class="flex min-h-11 items-center rounded px-2 hover:bg-gray-100 aria-[current=page]:font-semibold aria-[current=page]:text-brand-700 dark:hover:bg-gray-800 dark:aria-[current=page]:text-brand-300">{{ __($item['name']) }}</a></li>
                @endforeach
            </ul>
        </details>
    </nav>
    <x-main.cashflow.quick-transaction-button />
@endsection
