<x-layouts::app :title="__('Fasilitas Umum')">

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">
                    Fasilitas Umum
                </h1>
                <p class="mt-1 text-sm text-zinc-500">
                    Gunakan master fasilitas dari Admin dan perbarui realisasi pekerjaannya.
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <form
            method="GET"
            action="{{ route('pembangunan.common-facilities.index') }}"
            class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:flex-row sm:items-end dark:border-zinc-700 dark:bg-zinc-900"
        >
            <div class="flex-1">
                <label for="property_id" class="mb-2 block text-sm font-medium">Pilih Perumahan</label>
                <select
                    id="property_id"
                    name="property_id"
                    class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                >
                    <option value="">Pilih perumahan</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" @selected($propertyId === $property->id)>
                            {{ $property->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button
                    type="submit"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    Tampilkan
                </button>
                <a
                    href="{{ route('pembangunan.common-facilities.index') }}"
                    class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800"
                >
                    Reset
                </a>
            </div>
        </form>

        <section>
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Pilih Perumahan</h2>
                <p class="mt-1 text-sm text-zinc-500">Pilih perumahan untuk melihat fasilitas umumnya.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($properties as $property)
                    <a
                        href="{{ route('pembangunan.common-facilities.index', ['property_id' => $property->id]) }}"
                        class="rounded-xl border p-5 transition hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-zinc-800 {{ $propertyId === $property->id ? 'border-blue-500 bg-blue-50 dark:bg-zinc-800' : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' }}"
                        wire:navigate
                    >
                        <span class="block font-semibold text-zinc-900 dark:text-white">{{ $property->name }}</span>
                        <span class="mt-2 block text-sm text-zinc-500">
                            {{ $property->common_facilities_count }} fasilitas umum
                        </span>
                    </a>
                @empty
                    <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-sm text-zinc-500 dark:border-zinc-700">
                        Belum ada perumahan.
                    </div>
                @endforelse
            </div>
        </section>

        @if($selectedProperty)
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                        Fasilitas Umum — {{ $selectedProperty->name }}
                    </h2>
                    <p class="mt-1 text-sm text-zinc-500">Daftar fasilitas umum pada perumahan yang dipilih.</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-6 py-3">Fasilitas</th>
                                <th class="px-6 py-3">Anggaran</th>
                                <th class="px-6 py-3">Realisasi</th>
                                <th class="px-6 py-3">Progress</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($facilities as $facility)
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-medium">{{ $facility->name }}</div>
                                        @if($facility->description)
                                            <div class="mt-1 max-w-xs truncate text-xs text-zinc-500">{{ $facility->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">Rp {{ number_format((float) $facility->budget, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4">Rp {{ number_format((float) $facility->realization, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4">
                                        <div class="min-w-35">
                                            <div class="mb-1 flex justify-between text-xs">
                                                <span>{{ number_format($facility->progress, 2) }}%</span>
                                                <span>{{ number_format((float) $facility->budget, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                                <div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, max(0, $facility->progress)) }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">{{ $facility->status_label }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <a
                                            href="{{ route('pembangunan.common-facilities.show', $facility) }}"
                                            class="rounded-lg bg-zinc-900 px-3 py-2 text-xs font-medium text-white hover:bg-zinc-700"
                                            wire:navigate
                                        >
                                            Kelola
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-sm text-zinc-500">
                                        Belum ada fasilitas umum untuk perumahan ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($facilities->hasPages())
                    <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                        {{ $facilities->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>

</x-layouts::app>
