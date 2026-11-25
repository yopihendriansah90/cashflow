@extends('layouts.cashflow')

@section('content')
    @use('App\Support\Ledger')
    <x-common.page-breadcrumb :pageTitle="__('Audit transaksi')" />
    <x-common.component-card :title="__('Audit transaksi')">
        @forelse ($revisions as $revision)
            <details class="border-b border-gray-200 py-2 dark:border-gray-800">
                <summary class="min-h-11 cursor-pointer break-words">
                    #{{ $revision->transaction_id }} · {{ __(['create' => 'Dibuat', 'update' => 'Diubah', 'delete' => 'Dihapus'][$revision->action]) }} ·
                    {{ $holders[$revision->actor_membership_id]->user->name }} ·
                    {{ Ledger::auditTime($revision->created_at) }}
                </summary>
                @foreach (['before_snapshot' => 'Sebelum', 'after_snapshot' => 'Sesudah'] as $field => $label)
                    <p class="font-semibold">{{ __($label) }}</p>
                    <pre class="whitespace-pre-wrap break-all text-xs">{{ Ledger::auditSnapshot($revision->$field) }}</pre>
                @endforeach
            </details>
        @empty
            <p>{{ __('Belum ada perubahan.') }}</p>
        @endforelse
        <x-main.cashflow.pagination :paginator="$revisions" label="Halaman audit transaksi" />
    </x-common.component-card>
@endsection
