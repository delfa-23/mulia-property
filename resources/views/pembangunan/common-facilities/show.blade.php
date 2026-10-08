<x-layouts::app :title="__('Kelola Fasilitas Umum')">

    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <a
                href="{{ route('pembangunan.common-facilities.index') }}"
                class="text-sm font-medium text-zinc-500 hover:text-zinc-700"
                wire:navigate
            >
                Kembali ke Fasilitas Umum
            </a>
            <h1 class="mt-3 text-2xl font-semibold text-zinc-900 dark:text-white">
                {{ $commonFacility->name }}
            </h1>
            <p class="mt-1 text-sm text-zinc-500">
                {{ $commonFacility->property->name }}
            </p>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Anggaran</p>
                <p class="mt-2 text-lg font-semibold">Rp {{ number_format((float) $commonFacility->budget, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Realisasi</p>
                <p class="mt-2 text-lg font-semibold">Rp {{ number_format((float) $commonFacility->realization, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Progress</p>
                <p class="mt-2 text-lg font-semibold">{{ number_format($commonFacility->progress, 2) }}%</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Status</p>
                <p class="mt-2 text-lg font-semibold">{{ $commonFacility->status_label }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <form
                method="POST"
                action="{{ route('pembangunan.common-facilities.update', $commonFacility) }}"
                class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900"
            >
                @csrf
                @method('PUT')

                <div>
                    <h2 class="text-lg font-semibold">Perbarui Realisasi</h2>
                    <p class="mt-1 text-sm text-zinc-500">Fasilitas ini dibuat dan anggarannya ditentukan oleh Admin.</p>
                </div>

                <div>
                    <label for="realization" class="mb-2 block text-sm font-medium">Realisasi (Rp)</label>
                    <input
                        id="realization"
                        type="text"
                        name="realization"
                        value="{{ old('realization', $commonFacility->realization) }}"
                        inputmode="decimal"
                        data-currency-input
                        data-currency-min="0"
                        data-currency-max="{{ $commonFacility->budget }}"
                        data-currency-step="0.01"
                        required
                        class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                    >
                    @error('realization')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-zinc-500">Maksimal sesuai anggaran yang dibuat Admin.</p>
                </div>

                <div>
                    <label for="notes" class="mb-2 block text-sm font-medium">Catatan Update</label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                        placeholder="Contoh: Pekerjaan saluran tahap pertama selesai."
                    >{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    Simpan Realisasi
                </button>
            </form>

            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold">Riwayat Perubahan</h2>
                    <p class="mt-1 text-sm text-zinc-500">Catatan perubahan realisasi fasilitas ini.</p>
                </div>

                @if($commonFacility->histories->isEmpty())
                    <div class="rounded-lg border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">
                        Belum ada riwayat perubahan.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 text-xs uppercase text-zinc-500">
                                    <th class="px-3 py-3">Tanggal</th>
                                    <th class="px-3 py-3">Perubahan</th>
                                    <th class="px-3 py-3">Oleh</th>
                                    <th class="px-3 py-3">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @foreach($commonFacility->histories as $history)
                                    <tr>
                                        <td class="whitespace-nowrap px-3 py-3 text-zinc-600">{{ $history->created_at?->format('d/m/Y H:i') }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">
                                            Rp {{ number_format((float) $history->previous_realization, 0, ',', '.') }}
                                            <span class="mx-1 text-zinc-400">-&gt;</span>
                                            Rp {{ number_format((float) $history->realization, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-3 text-zinc-600">{{ $history->updatedBy?->name ?? '-' }}</td>
                                        <td class="px-3 py-3 text-zinc-600">{{ $history->notes ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>

</x-layouts::app>
