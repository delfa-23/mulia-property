<x-layouts::app :title="__('Data Sales')">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Data Sales</h1>
                <p class="mt-1 text-sm text-zinc-500">Kelola data Sales yang dapat dipilih pada booking. Sales tidak memiliki akun dashboard.</p>
            </div>
            <a href="{{ route('marketing.sales.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white" wire:navigate>Tambah Sales</a>
        </div>

        @if(session('success'))<div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>@endif

        <form method="GET" class="flex gap-2 rounded-xl border border-zinc-200 bg-white p-4">
            <input name="search" value="{{ request('search') }}" placeholder="Cari nama Sales" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
            <button class="rounded-lg bg-zinc-900 px-4 py-2 text-sm text-white">Cari</button>
        </form>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50"><tr><th class="px-6 py-3">Nama</th><th class="px-6 py-3">Telepon</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse($sales as $sale)
                            <tr><td class="px-6 py-4 font-medium">{{ $sale->name }}</td><td class="px-6 py-4">{{ $sale->phone ?? '-' }}</td><td class="px-6 py-4">{{ $sale->email ?? '-' }}</td><td class="px-6 py-4">{{ $sale->is_active ? 'Aktif' : 'Nonaktif' }}</td><td class="px-6 py-4 text-right"><a href="{{ route('marketing.sales.edit', $sale) }}" class="text-blue-600" wire:navigate>Edit</a><form class="ml-3 inline" method="POST" action="{{ route('marketing.sales.destroy', $sale) }}" onsubmit="return confirm('Hapus data Sales ini?')">@csrf @method('DELETE')<button class="text-red-600">Hapus</button></form></td></tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-zinc-500">Belum ada data Sales.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $sales->links() }}</div>
        </div>
    </div>
</x-layouts::app>
