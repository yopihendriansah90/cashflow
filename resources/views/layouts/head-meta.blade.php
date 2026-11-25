@inject('brand', 'App\Support\Brand')
@php
    $pageMeta = config('page-meta.' . str_replace('.', '\.', '/' . trim(request()->path(), '/')));
    if (request()->routeIs('password.reset')) {
        $pageMeta = config('page-meta./reset-kata-sandi');
    } elseif (request()->routeIs('households.settings')) {
        $pageMeta = config('page-meta./rumah-tangga');
    } elseif (request()->routeIs('ledger.index')) {
        $pageMeta = config('page-meta./catatan/'.request()->route('section'));
    }
    $pageTitle = explode(' | ', $pageMeta['title'] ?? $title ?? __('Beranda'))[0];
    $metaTitle = $pageTitle.' | '.$brand->name;
    $metaDescription = $pageMeta['description'] ?? 'This is Laravel ' . ($title ?? 'Dashboard') . ' page for TailAdmin - Laravel Tailwind CSS Admin Dashboard Template';
@endphp
<title>{{ $metaTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
<link rel="icon" href="{{ $brand->favicon }}">
