<x-layouts::app :title="__('Tambah Sales')">
    <div class="mx-auto max-w-3xl space-y-6">
        <div><a href="{{ route('marketing.sales.index') }}" class="text-sm text-zinc-500" wire:navigate>Kembali</a><h1 class="mt-3 text-2xl font-semibold">Tambah Sales</h1></div>
        <form method="POST" action="{{ route('marketing.sales.store') }}" class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6">@csrf @include('marketing.sales._form', ['sale' => null])<div class="flex justify-end gap-2"><a href="{{ route('marketing.sales.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm">Batal</a><button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">Simpan Sales</button></div></form>
    </div>
</x-layouts::app>
