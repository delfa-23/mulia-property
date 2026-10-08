<x-layouts::app :title="__('Keuangan Pembangunan')">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Keuangan Marketing</h1>
                <p class="mt-1 text-sm text-zinc-500">Pantau RAB, pemasukan, dan pengeluaran pembangunan.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('finance.budgets.create') }}" class="rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800" wire:navigate>
                    Tambah RAB
                </a>
                <a href="{{ route('finance.expense-transactions.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" wire:navigate>
                Tambah Pengeluaran
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('finance.index') }}" class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto]">
                <div>
                    <label for="finance_property_id" class="mb-2 block text-sm font-medium">Perumahan</label>
                    <select id="finance_property_id" name="property_id" data-property-select class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                        <option value="">Semua Project</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" @selected($propertyId == $property->id)>{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="finance_lot_id" class="mb-2 block text-sm font-medium">Blok / Kavling</label>
                    <select id="finance_lot_id" name="lot_id" data-lot-select disabled class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                        <option value="">{{ $propertyId ? 'Semua blok dan kavling pada perumahan ini' : 'Pilih perumahan terlebih dahulu' }}</option>
                        @foreach($lots as $lot)
                            <option value="{{ $lot->id }}" data-property-id="{{ $lot->block->property_id }}" @selected($lotId == $lot->id)>
                                Blok {{ $lot->block->name }} / Kavling {{ $lot->lot_number }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700">Tampilkan</button>
                <a href="{{ route('finance.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Reset</a>
            </div>
        </form>

        @php($deviation = $totalBudget - $totalExpense)
        @php($realizationPercentage = $totalBudget > 0 ? min(100, ($totalExpense / $totalBudget) * 100) : 0)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500">Total RAB</p><p class="mt-2 text-xl font-semibold">Rp {{ number_format($totalBudget, 0, ',', '.') }}</p></div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500">Total Pengeluaran</p><p class="mt-2 text-xl font-semibold">Rp {{ number_format($totalExpense, 0, ',', '.') }}</p></div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500">Depiasi</p><p class="mt-2 text-xl font-semibold {{ $deviation < 0 ? 'text-red-600' : 'text-green-600' }}">Rp {{ number_format($deviation, 0, ',', '.') }}</p></div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500">Realisasi</p><p class="mt-2 text-xl font-semibold">{{ number_format($realizationPercentage, 2) }}%</p></div>
        </div>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700"><h2 class="text-lg font-semibold">RAB / Budget</h2></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-zinc-50 dark:bg-zinc-800"><tr><th class="px-6 py-3">Nama</th><th class="px-6 py-3">Project</th><th class="px-6 py-3">Kavling/Fasilitas</th><th class="px-6 py-3">Kategori</th><th class="px-6 py-3">Periode</th><th class="px-6 py-3 text-right">Nominal</th></tr></thead><tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($budgets as $budget)
                    <tr>
                        <td class="px-6 py-4 font-medium">
                            {{ $budget->name }}
                            @if($budget->period_end?->lt(today()))
                                <span role="status" class="mt-1 block rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-950 dark:text-green-300">
                                    {{ $budget->name }} telah selesai — deadline {{ $budget->period_end->format('d/m/Y') }} terlewati.
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">{{ $budget->property?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $budget->lot ? 'Blok '.$budget->lot->block?->name.' / Kavling '.$budget->lot->lot_number : ($budget->facility?->name ?? 'Project umum') }}</td>
                        <td class="px-6 py-4">{{ $budget->category }}</td>
                        <td class="px-6 py-4">{{ $budget->period_start?->format('d/m/Y') ?? '-' }} - {{ $budget->period_end?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-6 py-4 text-right">Rp {{ number_format((float) $budget->amount, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-zinc-500">Belum ada data RAB.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="px-6 py-4">{{ $budgets->links() }}</div>
        </section>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700"><h2 class="text-lg font-semibold">Pengeluaran</h2></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-zinc-50 dark:bg-zinc-800"><tr><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Project</th><th class="px-6 py-3">Keterangan</th><th class="px-6 py-3">PIC</th><th class="px-6 py-3 text-right">Nominal</th><th class="px-6 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse($expenses as $expense)
                    <tr><td class="px-6 py-4">{{ $expense->transaction_date?->format('d/m/Y') }}</td><td class="px-6 py-4">{{ $expense->property?->name ?? '-' }}</td><td class="px-6 py-4"><div class="font-medium">{{ $expense->category }}</div><div class="text-xs text-zinc-500">{{ $expense->description ?: ($expense->lot ? 'Blok '.$expense->lot->block?->name.' / Kavling '.$expense->lot->lot_number : ($expense->facility?->name ?? '-')) }}</div></td><td class="px-6 py-4">{{ $expense->createdBy?->name ?? '-' }}</td><td class="px-6 py-4 text-right">Rp {{ number_format((float) $expense->amount, 0, ',', '.') }}</td><td class="px-6 py-4"><div class="flex justify-end gap-2"><a href="{{ route('finance.expense-transactions.edit', $expense) }}" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-medium" wire:navigate>Edit</a><form method="POST" action="{{ route('finance.expense-transactions.destroy', $expense) }}" onsubmit="return confirm('Hapus pengeluaran ini?')">@csrf @method('DELETE')<button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600">Hapus</button></form></div></td></tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-zinc-500">Belum ada pengeluaran.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="px-6 py-4">{{ $expenses->links() }}</div>
        </section>
    </div>
</x-layouts::app>
