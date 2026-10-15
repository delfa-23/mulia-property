<x-layouts::app :title="__('Edit RAB')">
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <a href="{{ route('admin.budgets.index') }}" class="text-sm text-zinc-500" wire:navigate>Kembali ke RAB</a>
            <h1 class="mt-3 text-2xl font-semibold">Edit RAB</h1>
        </div>
        <form method="POST" action="{{ route('admin.budgets.update', $budget) }}" class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6">
            @csrf
            @method('PUT')
            @include('admin.budgets._form')
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">Simpan Perubahan</button>
        </form>
    </div>
</x-layouts::app>
