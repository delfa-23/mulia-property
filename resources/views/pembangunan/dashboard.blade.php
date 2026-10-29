<x-layouts::app :title="'Dashboard Pembangunan'">
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-slate-950 px-6 py-5 text-white shadow-sm" style="background-color: #020617; color: #ffffff;">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-orange-300" style="color: #fdba74;">Ringkasan Operasional</p>
                <h1 class="mt-1 text-2xl font-bold !text-white" style="color: #ffffff;">Dashboard Pembangunan</h1>
                <p class="mt-1 text-sm text-slate-300" style="color: #cbd5e1;">Pantau anggaran, progress, dan status serah terima perumahan.</p>
            </div>
            <nav class="flex flex-wrap gap-2">
                <a href="{{ auth()->user()->role === 'admin' ? route('admin.property-construction-stages.index') : route('pembangunan.progress.index') }}" wire:navigate class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-slate-200" style="background-color: #ffffff; color: #0f172a;">
                    {{ auth()->user()->role === 'admin' ? 'Tahapan Pembangunan' : 'Progress Kavling' }}
                </a>
                <a href="{{ auth()->user()->role === 'admin' ? route('admin.common-facilities.index') : route('pembangunan.progress.facilities.index') }}" wire:navigate class="rounded-lg border border-slate-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800" style="border-color: #64748b; color: #ffffff;">
                    Fasilitas Umum
                </a>
            </nav>
        </header>

        <form method="GET" action="{{ route('pembangunan.dashboard') }}" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto] dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <label for="property_id" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-zinc-200">Perumahan</label>
                <select id="property_id" name="property_id" data-property-select class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                    <option value="">Semua Perumahan</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" @selected($propertyId === $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="lot_id" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-zinc-200">Kavling</label>
                <select id="lot_id" name="lot_id" data-lot-select disabled class="w-full rounded-lg border-slate-300 bg-white text-slate-900 disabled:cursor-not-allowed disabled:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:disabled:bg-zinc-800">
                    <option value="">Semua Kavling</option>
                    @foreach($lots as $lot)
                        <option value="{{ $lot->id }}" data-property-id="{{ $lot->block->property_id }}" @selected($lotId === $lot->id)>{{ $lot->block->name }}/{{ $lot->lot_number }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-orange-700">Terapkan</button>
                @if($propertyId || $lotId)
                    <a href="{{ route('pembangunan.dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Reset</a>
                @endif
            </div>
        </form>

        <section aria-labelledby="construction-finance-heading" class="overflow-hidden rounded-2xl border border-orange-600/30 bg-orange-600 text-white shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-white/15 bg-orange-600 px-5 py-4 sm:px-6">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-orange-100">Keuangan Pembangunan</p>
                    <h2 id="construction-finance-heading" class="mt-1 text-lg font-bold !text-white opacity-100" style="color: #ffffff !important; opacity: 1; -webkit-text-fill-color: #ffffff;">Ringkasan Anggaran</h2>
                </div>
                <span class="text-sm text-orange-50">{{ $selectedProperty?->name ?? 'Semua perumahan' }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left">
                    <thead class="border-b border-orange-500 bg-orange-700/20 text-xs font-semibold uppercase tracking-wide text-orange-50">
                        <tr>
                            <th scope="col" class="px-5 py-3 sm:px-6">Jenis</th>
                            <th scope="col" class="px-5 py-3 text-right sm:px-6">RAB</th>
                            <th scope="col" class="px-5 py-3 text-right sm:px-6">Total Pengeluaran</th>
                            <th scope="col" class="px-5 py-3 text-right sm:px-6">Deviasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-slate-900">
                        <tr>
                            <th scope="row" class="px-5 py-4 font-semibold text-slate-900 sm:px-6">Pembangunan</th>
                            <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">Rp {{ number_format((float) $constructionBudget, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">Rp {{ number_format((float) $constructionExpense, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-slate-900">Rp {{ number_format((float) $constructionDeviation, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="px-5 py-4 font-semibold text-slate-900 sm:px-6">Fasum</th>
                            <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">Rp {{ number_format((float) $totalFacilityBudget, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">Rp {{ number_format((float) $facilityExpense, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-slate-900">Rp {{ number_format((float) $facilityDeviation, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="bg-white font-bold">
                            <th scope="row" class="px-5 py-4 text-slate-900 sm:px-6">Total</th>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-bold sm:px-6">Rp {{ number_format((float) $totalBudget, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-bold sm:px-6">Rp {{ number_format((float) $totalExpense, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-bold text-slate-900">Rp {{ number_format((float) $totalBudgetDeviation, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-label="Ringkasan progress pembangunan" class="grid gap-3 sm:grid-cols-3">
            <article class="rounded-xl border border-slate-200 border-l-4 border-l-blue-600 bg-white p-5 shadow-sm dark:border-zinc-700 dark:border-l-blue-500 dark:bg-zinc-900">
                <p class="text-sm font-semibold text-slate-600 dark:text-zinc-300">Total Kavling</p>
                <p class="mt-2 text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($stats['total_lots']) }}</p>
            </article>
            <article class="rounded-xl border border-slate-200 border-l-4 border-l-orange-500 bg-white p-5 shadow-sm dark:border-zinc-700 dark:border-l-orange-400 dark:bg-zinc-900">
                <p class="text-sm font-semibold text-slate-600 dark:text-zinc-300">Rata-rata Progress Fisik</p>
                <p class="mt-2 text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($stats['physical_progress'], 2) }}<span class="ml-1 text-lg text-slate-500">%</span></p>
            </article>
            <article class="rounded-xl border border-slate-200 border-l-4 border-l-emerald-600 bg-white p-5 shadow-sm dark:border-zinc-700 dark:border-l-emerald-500 dark:bg-zinc-900">
                <p class="text-sm font-semibold text-slate-600 dark:text-zinc-300">Serah Terima Rumah</p>
                <p class="mt-2 text-3xl font-bold text-emerald-700 dark:text-emerald-400">{{ number_format($stats['finish_lots']) }}</p>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">Status Kavling</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-zinc-400">Proses berkas, SP3, dan akad mengikuti status booking. Serah terima dihitung setelah seluruh tahap aktif selesai.</p>
                @php($lotStatusGradientStops = $lotStatusChart->filter(fn ($status) => $status['value'] > 0)->map(fn ($status) => $status['color'].' '.$status['start_percent'].'% '.$status['end_percent'].'%')->implode(', '))
                <div role="img" aria-label="Grafik status {{ $lotStatusTotal }} kavling" class="mx-auto mt-5 flex size-40 items-center justify-center rounded-full p-3" style="background: conic-gradient(from -90deg, {{ $lotStatusGradientStops !== '' ? $lotStatusGradientStops : '#e2e8f0 0% 100%' }})">
                    <div class="flex size-full flex-col items-center justify-center rounded-full bg-white dark:bg-zinc-900">
                        <span class="text-3xl font-black text-slate-950 dark:text-white">{{ $lotStatusTotal }}</span>
                        <span class="text-xs font-semibold text-slate-600 dark:text-zinc-400">Total unit</span>
                    </div>
                </div>
                <div class="mt-5 space-y-2.5">
                    @foreach($lotStatusChart as $status)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="flex min-w-0 items-center gap-2 font-semibold text-slate-700 dark:text-zinc-300">
                                <span class="size-2.5 shrink-0 rounded-sm" style="background-color: {{ $status['color'] }}"></span>
                                <span class="truncate">{{ $status['label'] }}</span>
                            </span>
                            <span class="shrink-0 font-bold text-slate-950 dark:text-white">{{ $status['value'] }} <span class="font-medium text-slate-500 dark:text-zinc-400">({{ number_format($status['percent'], 1) }}%)</span></span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-950 dark:text-white">Tahapan Pembangunan</h2>
                            <p class="mt-1 text-sm text-slate-600 dark:text-zinc-400">Rata-rata progress per tahapan.</p>
                        </div>
                        <a href="{{ auth()->user()->role === 'admin' ? route('admin.property-construction-stages.index') : route('pembangunan.progress.index') }}" class="shrink-0 text-xs font-semibold text-orange-700 hover:underline dark:text-orange-300">Buka</a>
                    </div>
                    @if($stageProgress->isNotEmpty())
                        <div class="mt-5 space-y-4">
                            @foreach($stageProgress as $stage)
                                <div>
                                    <div class="mb-1.5 flex justify-between gap-3 text-sm">
                                        <span class="truncate font-semibold text-slate-700 dark:text-zinc-300">{{ $stage['name'] }}</span>
                                        <span class="shrink-0 font-bold text-slate-950 dark:text-white">{{ number_format($stage['progress'], 1) }}%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-orange-600" style="width: {{ min(100, max(0, $stage['progress'])) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-5 rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">Belum ada progress tahapan untuk filter ini.</p>
                    @endif
                </section>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-950 dark:text-white">Fasilitas Umum</h2>
                            <p class="mt-1 text-sm text-slate-600 dark:text-zinc-400">Progress fasilitas project.</p>
                        </div>
                        <a href="{{ auth()->user()->role === 'admin' ? route('admin.common-facilities.index') : route('pembangunan.progress.facilities.index') }}" class="shrink-0 text-xs font-semibold text-orange-700 hover:underline dark:text-orange-300">Buka</a>
                    </div>
                    @if($facilityProgressChart->isNotEmpty())
                        <div class="mt-5 space-y-4">
                            @foreach($facilityProgressChart as $facility)
                                <div>
                                    <div class="mb-1.5 flex justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate font-semibold text-slate-950 dark:text-white">{{ $facility['name'] }}<span class="ml-1 font-normal text-slate-950 dark:text-white">{{ $facility['property'] }}</span></span>
                                        <span class="shrink-0 font-bold text-slate-950 dark:text-white">{{ number_format($facility['progress'], 1) }}%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-blue-700" style="width: {{ min(100, max(0, $facility['progress'])) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-5 rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">Belum ada data fasilitas untuk filter ini.</p>
                    @endif
                </section>
            </div>
        </section>

        <x-site-plan-map
            :property="$selectedProperty"
            :lots="$sitePlanLots"
            :areas="$sitePlanAreas"
            :selected-lot-id="$lotId"
            :manage-url="null"
            :dashboard-url="route('pembangunan.dashboard')"
            detail-mode="modal"
            management-role="pembangunan"
            :wide="true"
        />
    </div>
</x-layouts::app>
