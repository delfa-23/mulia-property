<x-layouts::app :title="'Progress '.$facility->name">
    @php
        $remaining = $budget - (float) $facility->realization;
    @endphp
    <div class="space-y-6">
        <div>
            <a href="{{ route('pembangunan.progress.facilities.index') }}" class="text-sm font-medium text-zinc-500 hover:text-zinc-700" wire:navigate>← Kembali ke Progress Fasilitas Umum</a>
            <h1 class="mt-3 text-2xl font-bold text-zinc-900 dark:text-white">{{ $facility->name }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $facility->property?->name }}</p>
        </div>

        @if(session('success'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        <div class="grid gap-4 md:grid-cols-3">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Anggaran RAB</p>
                <p class="mt-2 text-xl font-semibold text-zinc-900 dark:text-white">Rp {{ number_format($budget, 0, ',', '.') }}</p>
            </section>
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Realisasi</p>
                <p class="mt-2 text-xl font-semibold text-zinc-900 dark:text-white">Rp {{ number_format((float) $facility->realization, 0, ',', '.') }}</p>
            </section>
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Progress</p>
                <p class="mt-2 text-xl font-semibold text-blue-600">{{ number_format($progress, 2) }}%</p>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div class="h-full rounded-full bg-blue-600" style="width: {{ $progress }}%"></div>
                </div>
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ (float) $facility->realization > 0 ? 'Edit Progress' : 'Tambah Progress' }}</h2>
                <p class="mt-1 text-sm text-zinc-500">Tambah, edit, atau hapus laporan progress. Data fasilitas dan anggarannya tetap dikelola Admin.</p>
                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-zinc-500">Status</dt><dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $facility->status_label }}</dd></div>
                    <div><dt class="text-zinc-500">Sisa Anggaran</dt><dd class="mt-1 font-medium text-zinc-900 dark:text-white">Rp {{ number_format($remaining, 0, ',', '.') }}</dd></div>
                </dl>

                @if($facility->status === 'cancelled')
                    <p class="mt-5 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">Fasilitas ini dibatalkan, progress tidak dapat diperbarui.</p>
                @else
                    <form method="POST" action="{{ (float) $facility->realization > 0 ? route('pembangunan.progress.facilities.update', $facility) : route('pembangunan.progress.facilities.store', $facility) }}" class="mt-5 space-y-4">
                        @csrf
                        @if((float) $facility->realization > 0)
                            @method('PUT')
                        @endif
                        <div>
                            <label for="realization" class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Total Realisasi (Rp)</label>
                            <input id="realization" name="realization" type="text" inputmode="decimal" data-currency-input data-currency-min="0" data-currency-max="{{ $budget }}" value="{{ old('realization', $facility->realization) }}" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                            @error('realization')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="notes" class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Catatan</label>
                            <textarea id="notes" name="notes" rows="3" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">{{ old('notes') }}</textarea>
                            @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">{{ (float) $facility->realization > 0 ? 'Simpan Perubahan' : 'Tambah Progress' }}</button>
                    </form>
                @endif
                @if((float) $facility->realization > 0)
                    <form
                        method="POST"
                        action="{{ route('pembangunan.progress.facilities.destroy', $facility) }}"
                        class="mt-3"
                        onsubmit="return confirm('Hapus progress saat ini? Riwayat perubahan tetap disimpan.')"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Hapus Progress</button>
                    </form>
                @endif
            </section>

            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">RAB Fasilitas</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500 dark:bg-zinc-800">
                            <tr><th class="px-5 py-3">Nama / Kategori</th><th class="px-5 py-3 text-right">Anggaran</th></tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($facility->budgets as $budgetItem)
                                <tr>
                                    <td class="px-5 py-3"><p class="font-medium text-zinc-900 dark:text-white">{{ $budgetItem->name }}</p><p class="text-xs text-zinc-500">{{ $budgetItem->category }}</p></td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-zinc-700 dark:text-zinc-300">Rp {{ number_format((float) $budgetItem->amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-5 py-8 text-center text-zinc-500">Belum ada detail RAB pembangunan untuk fasilitas ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Riwayat Progress</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500 dark:bg-zinc-800">
                        <tr><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Perubahan Realisasi</th><th class="px-5 py-3">Diubah Oleh</th><th class="px-5 py-3">Catatan</th></tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($facility->histories as $history)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 text-zinc-600 dark:text-zinc-300">{{ $history->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-zinc-700 dark:text-zinc-300">Rp {{ number_format((float) $history->previous_realization, 0, ',', '.') }} → <span class="font-medium">Rp {{ number_format((float) $history->realization, 0, ',', '.') }}</span></td>
                                <td class="px-5 py-4 text-zinc-700 dark:text-zinc-300">{{ $history->updatedBy?->name ?? '-' }}</td>
                                <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">{{ $history->notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-zinc-500">Belum ada riwayat progress fasilitas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
