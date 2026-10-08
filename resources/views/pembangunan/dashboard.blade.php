<x-layouts::app :title="'Dashboard Pembangunan'">

    <div class="space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Dashboard Pembangunan
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                Monitoring progress pembangunan seluruh project perumahan.
            </p>
        </div>

        <form method="GET" action="{{ route('pembangunan.dashboard') }}" class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3 dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <label for="property_id" class="mb-1 block text-sm font-medium text-gray-700 dark:text-zinc-300">Perumahan</label>
                <select id="property_id" name="property_id" data-property-select class="w-full rounded-lg border-gray-300 dark:border-zinc-700 dark:bg-zinc-800">
                    <option value="">Semua Perumahan</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" @selected($propertyId === $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="lot_id" class="mb-1 block text-sm font-medium text-gray-700 dark:text-zinc-300">Kavling</label>
                <select id="lot_id" name="lot_id" data-lot-select disabled class="w-full rounded-lg border-gray-300 dark:border-zinc-700 dark:bg-zinc-800">
                    <option value="">Semua Kavling</option>
                    @foreach($lots as $lot)
                        <option value="{{ $lot->id }}" data-property-id="{{ $lot->block->property_id }}" @selected($lotId === $lot->id)>{{ $lot->block->name }}/{{ $lot->lot_number }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Terapkan</button>
                @if($propertyId || $lotId)
                    <a href="{{ route('pembangunan.dashboard') }}" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 dark:text-zinc-300">Reset</a>
                @endif
            </div>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-gray-500">Project Perumahan</p>
                <p class="mt-2 text-3xl font-bold">
                    {{ $stats['total_properties'] }}
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-gray-500">Total Block</p>
                <p class="mt-2 text-3xl font-bold">
                    {{ $stats['total_blocks'] }}
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-gray-500">Total Kavling</p>
                <p class="mt-2 text-3xl font-bold">
                    {{ $stats['total_lots'] }}
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-gray-500">Kavling Finish</p>
                <p class="mt-2 text-3xl font-bold text-green-600">
                    {{ $stats['finish_lots'] }}
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-gray-500">Progress Fisik</p>
                <p class="mt-2 text-3xl font-bold text-blue-600">{{ number_format($stats['physical_progress'], 2) }}%</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-gray-500">Progress Fasilitas</p>
                <p class="mt-2 text-3xl font-bold text-blue-600">{{ number_format($stats['facility_progress'], 2) }}%</p>
            </div>

        </div>

        <div class="grid gap-5 xl:grid-cols-3">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-[#10233D]">Sebaran Status Kavling</h2>
                <p class="mt-1 text-sm text-slate-500">Status unit pada filter aktif.</p>
                @php($lotStatusGradientStops = $lotStatusChart->filter(fn ($status) => $status['value'] > 0)->map(fn ($status) => $status['color'].' '.$status['percent'].'%')->implode(', '))
                <div role="img" aria-label="Grafik status {{ $lotStatusTotal }} kavling" class="mx-auto mt-5 flex size-40 items-center justify-center rounded-full p-3" style="background: conic-gradient(from -90deg, {{ $lotStatusGradientStops !== '' ? $lotStatusGradientStops : '#e2e8f0 0% 100%' }})">
                    <div class="flex size-full flex-col items-center justify-center rounded-full bg-white">
                        <span class="text-3xl font-black text-[#10233D]">{{ $lotStatusTotal }}</span>
                        <span class="text-xs font-semibold text-slate-500">Total unit</span>
                    </div>
                </div>
                <div class="mt-4 space-y-2">
                    @foreach($lotStatusChart as $status)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="flex min-w-0 items-center gap-2 text-slate-600"><span class="size-2.5 shrink-0 rounded-sm" style="background-color: {{ $status['color'] }}"></span><span class="truncate">{{ $status['label'] }}</span></span>
                            <span class="shrink-0 font-bold text-[#10233D]">{{ $status['value'] }} <span class="font-medium text-slate-400">({{ number_format($status['percent'], 1) }}%)</span></span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-[#10233D]">Tren Progress Fisik</h2>
                        <p class="mt-1 text-sm text-slate-500">Rata-rata nilai update riwayat yang dicatat setiap bulan.</p>
                    </div>
                    <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-bold text-[#EB5120]">{{ $stats['physical_progress'] }}% rata-rata saat ini</span>
                </div>
                @if($monthlyPhysicalProgressChart->sum('updates') > 0)
                    <div role="img" aria-label="Grafik tren progress fisik enam bulan" class="mt-5 grid h-52 grid-cols-6 items-end gap-2 border-b border-slate-200 px-1 sm:gap-4">
                        @foreach($monthlyPhysicalProgressChart as $month)
                            @php($progressHeight = $month['progress'] !== null ? max(4, min(100, $month['progress'])) : 0)
                            <div class="flex h-full min-w-0 flex-col items-center justify-end gap-2">
                                <span class="text-[10px] font-bold text-[#10233D] sm:text-xs">{{ $month['progress'] !== null ? number_format($month['progress'], 1).'%' : '—' }}</span>
                                <div class="flex h-36 w-full items-end justify-center">
                                    <div class="w-1/2 rounded-t-md bg-[#EB5120]" style="height: {{ $progressHeight }}%" title="{{ $month['label'] }}: {{ $month['updates'] }} update"></div>
                                </div>
                                <span class="pb-2 text-center text-[10px] font-semibold text-slate-500 sm:text-xs">{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-5 flex h-52 items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-500">Belum ada riwayat progress enam bulan terakhir.</p>
                @endif
            </section>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-[#10233D]">Rata-rata per Tahap</h2>
                <p class="mt-1 text-sm text-slate-500">Progress terkini untuk setiap tahapan pembangunan.</p>
                @if($stageProgress->isNotEmpty())
                    <div class="mt-5 space-y-4">
                        @foreach($stageProgress as $stage)
                            <div>
                                <div class="mb-1.5 flex justify-between gap-3 text-sm"><span class="truncate font-semibold text-slate-600">{{ $stage['name'] }}</span><span class="shrink-0 font-bold text-[#10233D]">{{ number_format($stage['progress'], 2) }}%</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[#10233D]" style="width: {{ min(100, max(0, $stage['progress'])) }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-5 rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Belum ada progress per tahapan untuk filter ini.</p>
                @endif
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-[#10233D]">Progress Fasilitas Umum</h2>
                <p class="mt-1 text-sm text-slate-500">Fasilitas dengan progress tertinggi pada project terpilih.</p>
                @if($facilityProgressChart->isNotEmpty())
                    <div class="mt-5 space-y-4">
                        @foreach($facilityProgressChart as $facility)
                            <div>
                                <div class="mb-1.5 flex justify-between gap-3 text-sm"><span class="min-w-0 truncate font-semibold text-slate-600">{{ $facility['name'] }}<span class="ml-1 font-normal text-slate-400">{{ $facility['property'] }}</span></span><span class="shrink-0 font-bold text-[#EB5120]">{{ number_format($facility['progress'], 1) }}%</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[#EB5120]" style="width: {{ min(100, max(0, $facility['progress'])) }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-5 rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Belum ada data fasilitas untuk filter ini.</p>
                @endif
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">Update Progress Terbaru</h2>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($latestProgressUpdates as $progress)
                        <div class="flex items-center justify-between gap-4 py-3 text-sm">
                            <div><p class="font-medium">{{ $progress->lot?->block?->property?->name }} / {{ $progress->lot?->lot_number }}</p><p class="text-zinc-500">{{ $progress->stage?->name }} · {{ $progress->updatedBy?->name ?? '-' }}</p></div>
                            <span class="font-semibold text-blue-600">{{ number_format((float) $progress->progress, 2) }}%</span>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-zinc-500">Belum ada update progress.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">Update Fasilitas Terbaru</h2>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($latestFacilityUpdates as $facility)
                        <div class="flex items-center justify-between gap-4 py-3 text-sm">
                            <div><p class="font-medium">{{ $facility->name }}</p><p class="text-zinc-500">{{ $facility->property?->name }}</p></div>
                            <span class="font-semibold text-blue-600">{{ number_format($facility->progress, 2) }}%</span>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-zinc-500">Belum ada update fasilitas.</p>
                    @endforelse
                </div>
            </section>
        </div>

    </div>

</x-layouts::app>