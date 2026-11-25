@inject('brand', 'App\Support\Brand')

<span class="inline-flex min-w-0 items-center gap-2">
    <img src="{{ $brand->logo }}" width="32" height="32" alt="" class="size-8 shrink-0 object-contain">
    <span class="min-w-0 break-words font-semibold">{{ $brand->name }}</span>
</span>
