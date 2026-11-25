@props(['paginator', 'label', 'numbered' => false])

@if ($paginator->hasPages() && $numbered)
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $pages = collect([1, $current - 1, $current, $current + 1, $last])->filter(fn ($page) => $page >= 1 && $page <= $last)->unique()->sort()->values();
        $linkClass = 'inline-flex size-11 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:border-brand-400 hover:text-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:border-gray-700 dark:text-gray-300 dark:hover:text-brand-300';
    @endphp
    <nav data-cashbook-pagination aria-label="{{ __($label) }}" class="flex flex-wrap items-center gap-1">
        @if ($paginator->previousPageUrl())
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $linkClass }}" aria-label="{{ __('Sebelumnya') }}">
                <x-svg.chevron-left class="size-4 rtl:rotate-180" aria-hidden="true" />
            </a>
        @endif
        @foreach ($pages as $page)
            @if (! $loop->first && $page - $pages[$loop->index - 1] > 1)
                <span class="hidden px-1 text-gray-500 sm:inline dark:text-gray-400" aria-hidden="true">…</span>
            @endif
            @if ($page === $current)
                <span aria-current="page" aria-label="{{ __('Halaman :page', ['page' => $page]) }}"
                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-sm font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ $page }}</span>
            @else
                <a href="{{ $paginator->url($page) }}" aria-label="{{ __('Halaman :page', ['page' => $page]) }}"
                    class="{{ $linkClass }} {{ abs($page - $current) > 1 ? 'max-sm:hidden' : '' }}">{{ $page }}</a>
            @endif
        @endforeach
        @if ($paginator->nextPageUrl())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $linkClass }}" aria-label="{{ __('Selanjutnya') }}">
                <x-svg.chevron-right class="size-4 rtl:rotate-180" aria-hidden="true" />
            </a>
        @endif
    </nav>
@elseif ($paginator->hasPages())
    <nav aria-label="{{ __($label) }}" class="mt-4 flex flex-wrap items-center justify-between gap-2">
        @if ($paginator->previousPageUrl())
            <x-ui.button as="a" variant="outline" size="lg" rel="prev" href="{{ $paginator->previousPageUrl() }}">{{ __('Sebelumnya') }}</x-ui.button>
        @endif
        <span>{{ __('Halaman :current dari :last', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>
        @if ($paginator->nextPageUrl())
            <x-ui.button as="a" variant="outline" size="lg" rel="next" href="{{ $paginator->nextPageUrl() }}">{{ __('Selanjutnya') }}</x-ui.button>
        @endif
    </nav>
@endif
