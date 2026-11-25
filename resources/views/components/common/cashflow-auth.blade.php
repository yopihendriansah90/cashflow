@props(['heading', 'compact' => false])

<main @class([
    'mx-auto flex w-full max-w-md flex-col gap-5 text-gray-900 dark:text-gray-100',
    'min-h-dvh justify-center px-4 py-8' => ! $compact,
])>
    <header>
        <p class="mb-2 text-sm font-medium text-brand-700 dark:text-brand-300"><x-common.cashflow-brand /></p>
        <div class="flex items-center justify-between gap-3">
            <h1 class="min-w-0 break-words text-2xl font-semibold">{{ __($heading) }}</h1>
            @isset($actions)
                <div class="shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    </header>
    {{ $slot }}
</main>
