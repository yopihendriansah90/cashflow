@extends($membership ? 'layouts.cashflow' : 'layouts.fullscreen-layout')

@section('document-language', 'id')

@section('content')
    @inject('brand', 'App\Support\Brand')
    <x-common.cashflow-auth heading="Pengaturan identitas" :compact="(bool) $membership">
        <x-slot:actions>
            <x-common.cashflow-dialog heading="Pengaturan identitas" trigger="Ubah identitas" :open="! session('status')">
                <form method="POST" enctype="multipart/form-data" action="{{ route('identity.update') }}" class="space-y-4">
                    @csrf
                    <x-common.cashflow-field name="app_name" label="Nama aplikasi" :value="old('app_name', $brand->name)" maxlength="120" />
                    <label class="flex min-h-11 items-center gap-2">
                        <input type="checkbox" name="reset_name" value="1" @checked(old('reset_name'))>
                        <span>{{ __('Pulihkan nama bawaan') }}</span>
                    </label>
                    @foreach (['logo' => 'Logo', 'favicon' => 'Favicon'] as $kind => $label)
                        @php($default = asset($kind === 'logo' ? \App\Support\Brand::LOGO : \App\Support\Brand::FAVICON))
                        <div x-data="cashflowImage(@js($brand->$kind), @js($default), @js((bool) old('reset_'.$kind)))" class="space-y-2">
                            <img :src="preview" src="{{ $brand->$kind }}" class="size-16 object-contain" alt="{{ __('Pratinjau').' '.__($label) }}">
                            <label class="block">
                                <span>{{ __($label) }}</span>
                                <input class="block min-h-11 w-full cursor-pointer text-sm" name="{{ $kind }}" type="file"
                                    accept="{{ $kind === 'logo' ? 'image/png,image/webp' : 'image/png' }}" @change="select($event)" :disabled="reset">
                            </label>
                            @error($kind)
                                <p role="alert" class="text-sm text-error-700 dark:text-error-300">{{ $message }}</p>
                            @enderror
                            <label class="flex min-h-11 items-center gap-2">
                                <input type="checkbox" name="reset_{{ $kind }}" value="1" x-model="reset">
                                <span>{{ __('Pulihkan aset bawaan') }}</span>
                            </label>
                        </div>
                    @endforeach
                    @if ($errors->any())
                        <p class="text-sm">{{ __('Pilih kembali berkas yang ingin diunggah.') }}</p>
                    @endif
                    <x-common.cashflow-submit :cancel="true">{{ __('Simpan') }}</x-common.cashflow-submit>
                </form>
            </x-common.cashflow-dialog>
        </x-slot:actions>
        <x-ui.button as="a" variant="outline" size="lg" href="{{ route('dashboard') }}">{{ __('Kembali ke beranda') }}</x-ui.button>
        @unless (auth()->user()->activeMembership()->exists())
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-common.cashflow-submit>{{ __('Keluar dari akun') }}</x-common.cashflow-submit>
            </form>
        @endunless
    </x-common.cashflow-auth>
@endsection
