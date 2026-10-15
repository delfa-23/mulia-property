<x-layouts::app :title="__('Perumahan, Blok & Kavling')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Perumahan, Blok & Kavling</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Data dikelola oleh Admin. Tim Pembangunan dapat melihat informasi tanpa mengubahnya.</p>
        </div>

        <form method="GET" action="{{ route('pembangunan.properties.index') }}" class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:flex-row sm:items-end dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex-1">
                <label for="property_id" class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Pilih Perumahan</label>
                <select id="property_id" name="property_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                    <option value="">Semua perumahan</option>
                    @foreach($propertyOptions as $propertyOption)
                        <option value="{{ $propertyOption->id }}" @selected($propertyId === $propertyOption->id)>{{ $propertyOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Tampilkan</button>
                <a href="{{ route('pembangunan.properties.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 dark:border-zinc-600 dark:text-zinc-200">Reset</a>
            </div>
        </form>

        <div class="space-y-5">
            @forelse($properties as $property)
                <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        <div>
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $property->name }}</h2>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $property->address ?: 'Alamat belum diisi' }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full border border-orange-300 bg-orange-100 px-3 py-1 text-xs font-bold !text-orange-900 shadow-sm dark:border-orange-700 dark:bg-orange-950 dark:!text-orange-200">
                            {{ $property->blocks->count() }} blok · {{ $property->blocks->sum(fn ($block) => $block->lots->count()) }} kavling
                        </span>
                    </header>
                    <div class="grid gap-4 p-5 lg:grid-cols-2">
                        @forelse($property->blocks as $block)
                            <section class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <header class="flex items-center justify-between gap-3 bg-zinc-50 px-4 py-3 dark:bg-zinc-800">
                                    <h3 class="font-semibold text-zinc-900 dark:text-white">Blok {{ $block->name }}</h3>
                                    <span class="rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">{{ $block->lots->count() }} kavling</span>
                                </header>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                        <thead class="text-left text-xs uppercase text-zinc-500">
                                            <tr><th class="px-4 py-2.5">Kavling</th><th class="px-4 py-2.5">Harga Rumah</th><th class="px-4 py-2.5">Status</th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                            @forelse($block->lots as $lot)
                                                <tr>
                                                    <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $lot->lot_number }}</td>
                                                    <td class="whitespace-nowrap px-4 py-3 text-zinc-700 dark:text-zinc-300">Rp {{ number_format((float) $lot->house_price, 0, ',', '.') }}</td>
                                                    <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ ucfirst(str_replace('_', ' ', $lot->status)) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="px-4 py-6 text-center text-zinc-500">Belum ada kavling pada blok ini.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        @empty
                            <p class="rounded-lg border border-dashed border-zinc-300 p-6 text-sm text-zinc-500 dark:border-zinc-700">Belum ada blok di perumahan ini.</p>
                        @endforelse
                    </div>
                </section>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-900">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Belum ada data perumahan</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Data perumahan, blok, dan kavling akan tampil setelah ditambahkan oleh Admin.</p>
                </div>
            @endforelse
        </div>

        @if($properties->hasPages())
            <div>{{ $properties->links() }}</div>
        @endif
    </div>
</x-layouts::app>
