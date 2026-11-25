@props(['paginator', 'label'])

@if ($paginator->hasPages())
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
