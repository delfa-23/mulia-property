<x-layouts::app :title="__('RAB')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Rencana Anggaran Biaya</h1>
                <p class="mt-1 text-sm text-zinc-500">Kelola RAB pembangunan dan kebutuhan marketing.</p>
            </div>
            <a href="{{ route('admin.budgets.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white" wire:navigate>Tambah RAB</a>
        </div>

        @if(session('success'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('admin.budgets.index') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-200 bg-white p-4">
            <div>
                <label for="purpose" class="mb-2 block text-sm font-medium">Jenis RAB</label>
                <select id="purpose" name="purpose" class="rounded-lg border border-zinc-300 px-3 py-2">
                    <option value="">Semua</option>
                    <option value="pembangunan" @selected(($filters['purpose'] ?? '') === 'pembangunan')>Pembangunan</option>
                    <option value="marketing" @selected(($filters['purpose'] ?? '') === 'marketing')>Marketing</option>
                </select>
            </div>
            <div>
                <label for="property_id" class="mb-2 block text-sm font-medium">Perumahan</label>
                <select id="property_id" name="property_id" class="rounded-lg border border-zinc-300 px-3 py-2">
                    <option value="">Semua perumahan</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" @selected(($filters['property_id'] ?? '') == $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="block_id" class="mb-2 block text-sm font-medium">Blok</label>
                <select id="block_id" name="block_id" class="rounded-lg border border-zinc-300 px-3 py-2">
                    <option value="">Semua blok</option>
                    @foreach($blocks as $block)
                        <option value="{{ $block->id }}" @selected(($filters['block_id'] ?? '') == $block->id)>{{ $block->property?->name }} / Blok {{ $block->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="lot_id" class="mb-2 block text-sm font-medium">Kavling</label>
                <select id="lot_id" name="lot_id" class="rounded-lg border border-zinc-300 px-3 py-2">
                    <option value="">Semua kavling</option>
                    @foreach($lots as $lot)
                        <option value="{{ $lot->id }}" @selected(($filters['lot_id'] ?? '') == $lot->id)>{{ $lot->block?->property?->name }} / Blok {{ $lot->block?->name }} / Kavling {{ $lot->lot_number }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="facility_id" class="mb-2 block text-sm font-medium">Fasilitas Umum</label>
                <select id="facility_id" name="facility_id" class="rounded-lg border border-zinc-300 px-3 py-2">
                    <option value="">Semua fasilitas</option>
                    @foreach($facilities as $facility)
                        <option value="{{ $facility->id }}" @selected(($filters['facility_id'] ?? '') == $facility->id)>{{ $facility->property?->name }} / {{ $facility->name }}</option>
                    @endforeach
                </select>
            </div>
            <flux:button type="submit" variant="primary">Filter</flux:button>
            <a href="{{ route('admin.budgets.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm">Reset</a>
        </form>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-5 py-3">Jenis / Nama RAB</th>
                            <th class="px-5 py-3">Perumahan / Sasaran</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Periode</th>
                            <th class="px-5 py-3 text-right">Nominal</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse($budgets as $budget)
                            <tr>
                                <td class="px-5 py-4">
                                    <span class="mb-1 inline-flex rounded-full bg-zinc-100 px-2 py-1 text-xs">{{ ucfirst($budget->purpose) }}</span>
                                    <p class="font-medium">{{ $budget->name }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    {{ $budget->property?->name ?? 'Umum' }}
                                    <span class="block text-xs text-zinc-500">{{ $budget->lot ? 'Blok '.$budget->lot->block?->name.' / Kavling '.$budget->lot->lot_number : ($budget->facility?->name ?? 'Project umum') }}</span>
                                </td>
                                <td class="px-5 py-4">{{ $budget->category }}</td>
                                <td class="px-5 py-4">{{ $budget->period_start?->format('d/m/Y') ?? '-' }} – {{ $budget->period_end?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-5 py-4 text-right">Rp {{ number_format((float) $budget->amount, 0, ',', '.') }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.budgets.edit', $budget) }}" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs" wire:navigate>Edit</a>
                                        <form method="POST" action="{{ route('admin.budgets.destroy', $budget) }}" onsubmit="return confirm('Hapus RAB ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-600">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-zinc-500">Belum ada RAB.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4">{{ $budgets->links() }}</div>
        </div>
    </div>
</x-layouts::app>
