<div hidden data-app-notifications
    data-confirm="{{ __('Lanjutkan') }}"
    data-cancel="{{ __('Batal') }}"
    data-status="{{ session('status') }}"
    data-errors="{{ implode("\n", array_unique(isset($errors) ? $errors->all() : [])) }}"></div>
