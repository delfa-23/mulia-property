<x-layouts::app :title="'Dashboard Admin'">

    <div class="space-y-6">

        {{-- HEADER --}}
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Dashboard Admin
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                Ringkasan kondisi Project Perumahan dan operasional perusahaan.
            </p>
        </div>


        {{-- FILTER PROJECT --}}
        <form method="GET" action="{{ route('admin.dashboard') }}" class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3 dark:border-zinc-700 dark:bg-zinc-900">
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
                    <a href="{{ route('admin.dashboard') }}" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 dark:text-zinc-300">Reset</a>
                @endif
            </div>
        </form>


        {{-- MASTER DATA --}}
        <div>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
                Project Perumahan
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">

                {{-- PROJECT --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Project Perumahan
                    </p>

                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $stats['total_properties'] }}
                    </p>

                    <a
                        href="{{ route('admin.properties.index') }}"
                        class="mt-3 inline-block text-sm font-medium text-blue-600 hover:text-blue-700"
                    >
                        Kelola Project →
                    </a>
                </div>

                {{-- BLOCK --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Total Block
                    </p>

                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $stats['total_blocks'] }}
                    </p>
                </div>

                {{-- KAVLING --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Total Kavling
                    </p>

                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $stats['total_lots'] }}
                    </p>
                </div>

            </div>
        </div>


        {{-- STATUS KAVLING --}}
        <div>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
                Status Kavling
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">

                {{-- AVAILABLE --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Tersedia
                    </p>

                    <p class="mt-2 text-2xl font-bold text-green-600">
                        {{ $stats['available_lots'] }}
                    </p>
                </div>

                {{-- BOOKING --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Booking
                    </p>

                    <p class="mt-2 text-2xl font-bold text-blue-600">
                        {{ $stats['booked_lots'] }}
                    </p>
                </div>

                {{-- PROCESS --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Proses Berkas
                    </p>

                    <p class="mt-2 text-2xl font-bold text-purple-600">
                        {{ $stats['process_lots'] }}
                    </p>
                </div>

                {{-- AKAD --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Akad
                    </p>

                    <p class="mt-2 text-2xl font-bold text-orange-600">
                        {{ $stats['akad_lots'] }}
                    </p>
                </div>

                {{-- FINISH --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Finish
                    </p>

                    <p class="mt-2 text-2xl font-bold text-gray-700 dark:text-zinc-200">
                        {{ $stats['finish_lots'] }}
                    </p>
                </div>

            </div>
        </div>


        {{-- DASHBOARD CHARTS --}}
        <div class="grid gap-5 xl:grid-cols-3">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-[#10233D]">Arus Kas 6 Bulan</h2>
                        <p class="mt-1 text-sm text-slate-500">Perbandingan pemasukan dan pengeluaran pada filter aktif.</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs font-semibold text-slate-600">
                        <span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-[#EB5120]"></span>Pemasukan</span>
                        <span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-[#10233D]"></span>Pengeluaran</span>
                    </div>
                </div>

                @if($monthlyFinancialChart->sum('income') + $monthlyFinancialChart->sum('expense') > 0)
                    <div role="img" aria-label="Grafik arus kas enam bulan" class="mt-6 grid h-56 grid-cols-6 items-end gap-2 border-b border-slate-200 px-1 sm:gap-4">
                        @foreach($monthlyFinancialChart as $month)
                            @php
                                $incomeHeight = $month['income'] > 0 ? max(4, ($month['income'] / $monthlyFinancialChartMax) * 100) : 0;
                                $expenseHeight = $month['expense'] > 0 ? max(4, ($month['expense'] / $monthlyFinancialChartMax) * 100) : 0;
                            @endphp
                            <div class="flex h-full min-w-0 flex-col items-center justify-end gap-2">
                                <div class="flex h-40 w-full items-end justify-center gap-1 sm:gap-2">
                                    <div class="w-1/3 rounded-t bg-[#EB5120]" style="height: {{ $incomeHeight }}%" title="{{ $month['label'] }}: pemasukan Rp {{ number_format($month['income'], 0, ',', '.') }}"></div>
                                    <div class="w-1/3 rounded-t bg-[#10233D]" style="height: {{ $expenseHeight }}%" title="{{ $month['label'] }}: pengeluaran Rp {{ number_format($month['expense'], 0, ',', '.') }}"></div>
                                </div>
                                <span class="pb-2 text-center text-[10px] font-semibold text-slate-500 sm:text-xs">{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-6 flex h-56 items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-500">
                        Belum ada transaksi keuangan dalam enam bulan terakhir.
                    </div>
                @endif

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-orange-50 p-3">
                        <p class="text-xs font-semibold text-slate-500">Total pemasukan</p>
                        <p class="mt-1 text-lg font-black text-[#EB5120]">Rp {{ number_format($stats['total_income'], 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xs font-semibold text-slate-500">Total pengeluaran</p>
                        <p class="mt-1 text-lg font-black text-[#10233D]">Rp {{ number_format($stats['total_expense'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div>
                    <h2 class="text-lg font-bold text-[#10233D]">Komposisi Kavling</h2>
                    <p class="mt-1 text-sm text-slate-500">Sebaran status pada filter aktif.</p>
                </div>

                <div role="img" aria-label="Komposisi {{ $lotStatusTotal }} kavling" class="mx-auto mt-5 flex size-44 items-center justify-center rounded-full p-3" style="background: {{ $lotStatusGradient }}">
                    <div class="flex size-full flex-col items-center justify-center rounded-full bg-white">
                        <span class="text-3xl font-black text-[#10233D]">{{ number_format($lotStatusTotal) }}</span>
                        <span class="text-xs font-semibold text-slate-500">Total unit</span>
                    </div>
                </div>

                <div class="mt-5 space-y-2">
                    @foreach($lotStatusChart as $status)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="flex min-w-0 items-center gap-2 text-slate-600">
                                <span class="size-2.5 shrink-0 rounded-sm" style="background-color: {{ $status['color'] }}"></span>
                                <span class="truncate">{{ $status['label'] }}</span>
                            </span>
                            <span class="shrink-0 font-bold text-[#10233D]">{{ $status['value'] }} <span class="font-medium text-slate-400">({{ number_format($status['percent'], 1) }}%)</span></span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-[#10233D]">Progress Pembangunan per Tahapan</h2>
                    <p class="mt-1 text-sm text-slate-500">Rata-rata progress tahapan pembangunan pada project terpilih.</p>
                </div>
                <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-bold text-[#EB5120]">{{ number_format($stats['physical_progress'], 2) }}% rata-rata fisik</span>
            </div>

            @if($stageProgress->isNotEmpty())
                <div class="mt-5 grid gap-x-8 gap-y-4 md:grid-cols-2">
                    @foreach($stageProgress as $stage)
                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                <span class="truncate font-semibold text-slate-700">{{ $stage['name'] }}</span>
                                <span class="shrink-0 font-bold text-[#10233D]">{{ number_format($stage['progress'], 2) }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-[#EB5120]" style="width: {{ min(100, max(0, $stage['progress'])) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-5 rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Belum ada data progress pembangunan untuk filter ini.</p>
            @endif
        </section>

        {{-- BOOKING & PROCESS --}}
        <div>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
                Proses Penjualan
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Total Booking
                    </p>

                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $stats['total_bookings'] }}
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Proses Berkas
                    </p>

                    <p class="mt-2 text-2xl font-bold text-purple-600">
                        {{ $stats['process_bookings'] }}
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Akad
                    </p>

                    <p class="mt-2 text-2xl font-bold text-orange-600">
                        {{ $stats['akad_bookings'] }}
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Finish
                    </p>

                    <p class="mt-2 text-2xl font-bold text-green-600">
                        {{ $stats['finish_bookings'] }}
                    </p>
                </div>

            </div>
        </div>


        {{-- KEUANGAN --}}
        <div>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
                Keuangan
            </h2>

            <div class="grid gap-4 md:grid-cols-2">

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Uang Masuk
                    </p>

                    <p class="mt-2 text-2xl font-bold text-green-600">
                        Rp {{ number_format($stats['total_income'], 0, ',', '.') }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-gray-500">RAB</p><p class="mt-2 text-2xl font-bold">Rp {{ number_format($stats['total_budget'], 0, ',', '.') }}</p></div>
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-gray-500">Depiasi</p><p class="mt-2 text-2xl font-bold {{ $stats['deviation'] < 0 ? 'text-red-600' : 'text-green-600' }}">Rp {{ number_format($stats['deviation'], 0, ',', '.') }}</p></div>
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-gray-500">Progress Fisik / Fasilitas</p><p class="mt-2 text-2xl font-bold text-blue-600">{{ number_format($stats['physical_progress'], 2) }}% / {{ number_format($stats['facility_progress'], 2) }}%</p></div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Pengeluaran
                    </p>

                    <p class="mt-2 text-2xl font-bold text-red-600">
                        Rp {{ number_format($stats['total_expense'], 0, ',', '.') }}
                    </p>
                </div>

            </div>
        </div>


        {{-- ALERT --}}
        <div>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
                Monitoring
            </h2>

            <div class="grid gap-4 md:grid-cols-2">

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Alert / Misscom Aktif
                    </p>

                    <p class="mt-2 text-2xl font-bold text-red-600">
                        {{ $stats['active_alerts'] }}
                    </p>

                    <p class="mt-2 text-xs text-gray-400">
                        Perlu diperiksa oleh tim terkait.
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Laporan Progress Pending
                    </p>

                    <p class="mt-2 text-2xl font-bold text-yellow-600">
                        {{ $stats['pending_reports'] }}
                    </p>

                    <p class="mt-2 text-xs text-gray-400">
                        Menunggu approval atau revisi.
                    </p>
                </div>

            </div>
        </div>


        {{-- LATEST ACTIVITY --}}
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-[#10233D]">Booking Terbaru</h2>
                        <p class="mt-1 text-xs text-slate-500">Aktivitas penjualan terbaru.</p>
                    </div>
                    <a href="{{ route('marketing.bookings.index') }}" class="shrink-0 text-xs font-bold text-[#EB5120] hover:underline">Lihat semua</a>
                </div>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse($recentBookings as $booking)
                        <div class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-[#10233D]">{{ $booking->customer?->name ?? 'Customer' }}</p>
                                <p class="mt-0.5 truncate text-xs text-slate-500">{{ $booking->lot?->block?->name }}/{{ $booking->lot?->lot_number }} · {{ $booking->lot?->block?->property?->name }}</p>
                                <p class="mt-1 text-[11px] text-slate-400">{{ $booking->booking_date?->format('d/m/Y') ?? '-' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-orange-50 px-2.5 py-1 text-[10px] font-bold capitalize text-[#EB5120]">{{ str_replace('_', ' ', $booking->status) }}</span>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-500">Belum ada booking untuk filter ini.</p>
                    @endforelse
                </div>
            </section>

            <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div>
                    <h2 class="text-base font-bold text-[#10233D]">Progress Terbaru</h2>
                    <p class="mt-1 text-xs text-slate-500">Pembaruan terakhir per tahapan.</p>
                </div>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse($recentProgress as $progress)
                        <div class="py-3 first:pt-0 last:pb-0">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-[#10233D]">{{ $progress->stage?->name ?? 'Tahapan' }} · {{ $progress->lot?->block?->name }}/{{ $progress->lot?->lot_number }}</p>
                                    <p class="mt-0.5 truncate text-xs text-slate-500">{{ $progress->lot?->block?->property?->name }} · {{ $progress->updatedBy?->name ?? '-' }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-black text-[#EB5120]">{{ number_format((float) $progress->progress, 1) }}%</span>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400">{{ $progress->updated_at?->format('d/m/Y H:i') ?? '-' }}</p>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-500">Belum ada pembaruan progress.</p>
                    @endforelse
                </div>
            </section>

            <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div>
                    <h2 class="text-base font-bold text-[#10233D]">Alert Aktif</h2>
                    <p class="mt-1 text-xs text-slate-500">Perlu tindak lanjut tim terkait.</p>
                </div>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse($recentAlerts as $alert)
                        <div class="py-3 first:pt-0 last:pb-0">
                            <div class="flex items-start justify-between gap-3">
                                <p class="min-w-0 truncate text-sm font-bold text-[#10233D]">{{ $alert->title }}</p>
                                <span class="shrink-0 rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold capitalize text-rose-700">{{ $alert->severity }}</span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $alert->message }}</p>
                            <p class="mt-1 truncate text-[11px] text-slate-400">{{ $alert->lot?->block?->property?->name ?? 'Umum' }} · {{ $alert->createdBy?->name ?? '-' }}</p>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-500">Tidak ada alert aktif untuk filter ini.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="rounded-xl border border-gray-300 bg-gray-50 p-6 dark:border-zinc-700 dark:bg-zinc-900/50">

            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Weekly Progress Seluruh Divisi
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-zinc-400">
                {{ $stats['weekly_reports']->count() }} laporan mingguan terbaru tersedia.
            </p>

            <div class="mt-4 space-y-2">
                @foreach($stats['weekly_reports'] as $weeklyReport)
                    <div class="flex justify-between text-sm"><span>{{ $weeklyReport->week_start->format('d/m/Y') }} - {{ $weeklyReport->week_end->format('d/m/Y') }}</span><span>{{ ucfirst($weeklyReport->status) }}</span></div>
                @endforeach
            </div>

        </div>

    </div>

</x-layouts::app>