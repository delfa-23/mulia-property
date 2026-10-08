<x-layouts::app :title="'Progress Kavling'">

    <div class="space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Progress Kavling
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                Monitoring progress pembangunan berdasarkan project dan kavling.
            </p>
        </div>


        {{-- FILTER --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">

            <form method="GET" class="grid gap-4 md:grid-cols-3">

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                        Nama Perumahan
                    </label>

                    <select
                        name="property_id"
                        data-property-select
                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-gray-900 hover:bg-white hover:text-gray-900 focus:bg-white focus:text-gray-900 dark:border-zinc-700"
                    >
                        <option value="">
                            Semua Perumahan
                        </option>

                        @foreach($properties as $property)
                            <option
                                value="{{ $property->id }}"
                                @selected(request('property_id') == $property->id)
                            >
                                {{ $property->name }}
                            </option>
                        @endforeach
                    </select>
                </div>


                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                        Kavling
                    </label>

                    <select
                        name="lot_id"
                        data-lot-select
                        disabled
                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-gray-900 hover:bg-white hover:text-gray-900 focus:bg-white focus:text-gray-900 dark:border-zinc-700"
                    >
                        <option value="">
                            Semua Kavling
                        </option>

                        @foreach($lotOptions as $lotOption)
                            <option
                                value="{{ $lotOption->id }}"
                                data-property-id="{{ $lotOption->block->property_id }}"
                                @selected(request('lot_id') == $lotOption->id)
                            >
                                {{ $lotOption->block->name }}/{{ $lotOption->lot_number }}
                            </option>
                        @endforeach
                    </select>
                </div>


                <div class="flex items-end">
                    <div class="flex w-full gap-2">
                        <button type="submit" class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            Terapkan
                        </button>
                        <a
                            href="{{ route('pembangunan.progress.index') }}"
                            class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-zinc-700 dark:text-zinc-300"
                        >
                            Reset Filter
                        </a>
                    </div>
                </div>

            </form>

        </div>


        {{-- TABLE --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">

            <div class="overflow-x-auto">

                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">

                    <thead class="bg-gray-50 dark:bg-zinc-800">

                        <tr>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Perumahan
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Kavling
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Konsumen
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                RAB
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Progress Fisik
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Realisasi
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Depiasi
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Status
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Aksi
                            </th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">

                        @forelse($lots as $lot)

                            <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">

                                <td class="px-5 py-4 text-sm text-gray-900 dark:text-white">
                                    {{ $lot->block->property->name }}
                                </td>

                                <td class="px-5 py-4 text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $lot->block->name }}/{{ $lot->lot_number }}
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700 dark:text-zinc-300">
                                    {{ optional($lot->bookings->first()?->customer)->name ?? '-' }}
                                </td>

                                @php
                                    $rab = $lot->budgets->sum('amount');

                                    $realisasi = $lot->expenseTransactions->sum('amount');

                                    $depiasi = $rab - $realisasi;

                                    $progressFisik = (float) ($lot->constructionProgresses->whereNotNull('stage_id')->max('progress') ?? 0);
                                @endphp

                                <td class="px-5 py-4 text-sm text-gray-700 dark:text-zinc-300">
                                    Rp {{ number_format($rab, 0, ',', '.') }}
                                </td>

                                <td class="px-5 py-4 text-sm">

                                    <div class="flex items-center gap-3">

                                        <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200 dark:bg-zinc-700">
                                            <div
                                                class="h-full rounded-full bg-blue-600"
                                                style="width: {{ min(100, max(0, $progressFisik)) }}%"
                                            ></div>
                                        </div>

                                        <span class="font-medium">
                                            {{ number_format($progressFisik, 2) }}%
                                        </span>

                                    </div>

                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700 dark:text-zinc-300">
                                    Rp {{ number_format($realisasi, 0, ',', '.') }}
                                </td>

                                <td class="px-5 py-4 text-sm font-medium
                                    {{ $depiasi >= 0 ? 'text-green-600' : 'text-red-600' }}"
                                >
                                    Rp {{ number_format($depiasi, 0, ',', '.') }}
                                </td>

                                <td class="px-5 py-4 text-sm">

                                    @php
                                        $statusLabels = [
                                            'available' => 'Tersedia',
                                            'blocked' => 'Blocked',
                                            'booked' => 'Booking',
                                            'process' => 'Proses',
                                            'akad' => 'Akad',
                                            'finish' => 'Finish',
                                            'cancelled' => 'Dibatalkan',
                                        ];
                                    @endphp

                                    {{ $statusLabels[$lot->status] ?? ucfirst($lot->status) }}

                                </td>

                                <td class="px-5 py-4 text-sm">
                                    <a
                                        href="{{ route('pembangunan.progress.show', [
                                            'property' => $lot->block->property,
                                            'block' => $lot->block,
                                            'lot' => $lot,
                                        ]) }}"
                                        class="inline-flex items-center rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700"
                                    >
                                        Detail Progress
                                    </a>
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="9"
                                    class="px-5 py-10 text-center text-sm text-gray-500"
                                >
                                    Belum ada data kavling.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

            @if($lots->hasPages())
                <div class="border-t border-gray-200 px-5 py-4 dark:border-zinc-700">
                    {{ $lots->links() }}
                </div>
            @endif

        </div>

    </div>

</x-layouts::app>