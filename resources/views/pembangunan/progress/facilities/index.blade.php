<x-layouts::app :title="__('Progress Fasilitas Umum')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Progress Fasilitas Umum</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Pantau anggaran, realisasi, dan progress fasilitas umum perumahan.</p>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Pilih fasilitas untuk menambah, mengubah, atau menghapus laporan progress. Data fasilitas tetap dikelola Admin.</p>
        </div>

        <form method="GET" action="{{ route('pembangunan.progress.facilities.index') }}" class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:flex-row sm:items-end dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex-1">
                <label for="property_id" class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Perumahan</label>
                <select id="property_id" name="property_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                    <option value="">Semua perumahan</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" @selected($propertyId === $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Terapkan</button>
                <a href="{{ route('pembangunan.progress.facilities.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 dark:border-zinc-600 dark:text-zinc-200">Reset</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            <th class="px-5 py-3">Perumahan</th>
                            <th class="px-5 py-3">Fasilitas Umum</th>
                            <th class="px-5 py-3">Anggaran</th>
                            <th class="px-5 py-3">Realisasi</th>
                            <th class="px-5 py-3">Progress</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($facilities as $facility)
                            @php
                                $budget = (float) $facility->pembangunan_budget_total > 0
                                    ? (float) $facility->pembangunan_budget_total
                                    : (float) $facility->budget;
                                $progress = $budget > 0
                                    ? min(100, ((float) $facility->realization / $budget) * 100)
                                    : 0;
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ $facility->property?->name }}</td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-zinc-900 dark:text-white">{{ $facility->name }}</p>
                                    @if($facility->description)
                                        <p class="mt-1 max-w-sm truncate text-xs text-zinc-500">{{ $facility->description }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">Rp {{ number_format($budget, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">Rp {{ number_format((float) $facility->realization, 0, ',', '.') }}</td>
                                <td class="px-5 py-4">
                                    <div class="min-w-36">
                                        <div class="mb-1 flex justify-between gap-3 text-xs text-zinc-600 dark:text-zinc-300">
                                            <span>{{ number_format($progress, 2) }}%</span>
                                            <span>Rp {{ number_format($budget, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                            <div class="h-full rounded-full bg-blue-600" style="width: {{ $progress }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ $facility->status_label }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('pembangunan.progress.facilities.show', $facility) }}" class="rounded-lg bg-zinc-900 px-3 py-2 text-xs font-medium text-white hover:bg-zinc-700 dark:bg-zinc-700 dark:hover:bg-zinc-600" wire:navigate>{{ (float) $facility->realization > 0 ? 'Kelola Progress' : 'Tambah Progress' }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-zinc-500">Belum ada fasilitas umum pada perumahan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($facilities->hasPages())
                <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">{{ $facilities->links() }}</div>
            @endif
        </div>
    </div>
</x-layouts::app>
