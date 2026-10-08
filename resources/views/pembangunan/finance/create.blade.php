<x-layouts::app :title="__('Tambah Pengeluaran')">
    <div class="mx-auto max-w-3xl space-y-6">
        <div><a href="{{ route('finance.index') }}" class="text-sm text-zinc-500" wire:navigate>Kembali ke Keuangan</a><h1 class="mt-3 text-2xl font-semibold">Tambah Pengeluaran</h1></div>
        <form method="POST" action="{{ route('finance.expense-transactions.store') }}" class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            @csrf
            @include('pembangunan.finance._form', ['expenseTransaction' => null])
            <div class="flex justify-end"><button type="submit" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">Simpan Pengeluaran</button></div>
        </form>
    </div>
</x-layouts::app>
