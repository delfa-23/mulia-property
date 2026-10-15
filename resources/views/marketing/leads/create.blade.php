<x-layouts::app :title="__('Tambah Prospek')">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <a href="{{ route('marketing.leads.index') }}" class="text-sm text-zinc-500" wire:navigate>Kembali</a>
            <h1 class="mt-3 text-2xl font-semibold">Tambah Prospek</h1>
            <p class="mt-1 text-sm text-zinc-500">Catat informasi lead, kategori, dan sumber kedatangannya.</p>
        </div>
        <form method="POST" action="{{ route('marketing.leads.store') }}" class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            @csrf
            @include('marketing.leads._form')
            <button class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">Simpan Prospek</button>
        </form>
    </div>
</x-layouts::app>
