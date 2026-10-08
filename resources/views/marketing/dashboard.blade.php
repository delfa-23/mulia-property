<x-layouts::app :title="__('Dashboard Marketing')">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-[#10233D]">Dashboard Marketing</h1>
                <p class="mt-1 text-sm text-zinc-500">Monitoring kavling, customer, booking, dan ringkasan keuangan.</p>
            </div>

            <form
                method="GET"
                action="{{ route('marketing.dashboard') }}"
                class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm sm:grid-cols-2 xl:min-w-136 xl:grid-cols-[1fr_1fr_auto]"
            >
                <div>
                    <label for="property_id" class="mb-1 block text-sm font-medium text-zinc-700">Perumahan</label>
                    <select
                        id="property_id"
                        name="property_id"
                        data-property-select
                        class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <option value="">Semua Perumahan</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" @selected($propertyId === $property->id)>
                                {{ $property->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="lot_id" class="mb-1 block text-sm font-medium text-zinc-700">Kavling</label>
                    <select
                        id="lot_id"
                        name="lot_id"
                        data-lot-select
                        disabled
                        class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <option value="">Semua Kavling</option>
                        @foreach($lots as $lot)
                            <option
                                value="{{ $lot->id }}"
                                data-property-id="{{ $lot->block->property_id }}"
                                @selected($lotId === $lot->id)
                            >
                                {{ $lot->block->name }}/{{ $lot->lot_number }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    >
                        Terapkan
                    </button>
                    @if($propertyId || $lotId)
                        <a
                            href="{{ route('marketing.dashboard') }}"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300"
                        >
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <section class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-[#10233D]">Ringkasan Keuangan</h2>
                    <p class="text-sm text-zinc-500">Ringkasan RAB, realisasi pengeluaran, dan deviasi sesuai filter.</p>
                </div>
                <a
                    href="{{ route('finance.index') }}"
                    class="inline-flex w-fit items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    wire:navigate
                >
                    Kelola Keuangan
                </a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article class="min-w-0 rounded-xl border border-zinc-200 border-l-4 border-l-blue-500 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-500">Total RAB</p>
                    <p class="mt-3 wrap-break-word text-xl font-bold tabular-nums text-[#10233D]">
                        Rp {{ number_format((float) $financialStats['total_budget'], 0, ',', '.') }}
                    </p>
                    <p class="mt-2 text-xs text-zinc-500">Anggaran yang telah disusun</p>
                </article>

                <article class="min-w-0 rounded-xl border border-zinc-200 border-l-4 border-l-rose-500 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-500">Total Pengeluaran</p>
                    <p class="mt-3 wrap-break-word text-xl font-bold tabular-nums text-rose-700">
                        Rp {{ number_format((float) $financialStats['total_expense'], 0, ',', '.') }}
                    </p>
                    <p class="mt-2 text-xs text-zinc-500">Akumulasi transaksi pengeluaran</p>
                </article>

                <article class="min-w-0 rounded-xl border border-zinc-200 border-l-4 border-l-amber-500 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-500">Deviasi RAB</p>
                    <p class="mt-3 wrap-break-word text-xl font-bold tabular-nums {{ $financialStats['deviation'] < 0 ? 'text-rose-700' : 'text-amber-700' }}">
                        Rp {{ number_format((float) $financialStats['deviation'], 0, ',', '.') }}
                    </p>
                    <p class="mt-2 text-xs text-zinc-500">RAB dikurangi pengeluaran</p>
                </article>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-[#10233D]">Pengeluaran Terbaru</h2>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($recentExpenses as $expense)
                        <div class="flex justify-between gap-4 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-zinc-800">{{ $expense->category }}</p>
                                <p class="mt-1 truncate text-zinc-500">
                                    {{ $expense->property?->name ?? 'Project umum' }}
                                    ·
                                    {{ $expense->lot?->lot_number ?? $expense->facility?->name ?? 'Tanpa objek' }}
                                </p>
                                <p class="mt-1 text-xs text-zinc-400">
                                    {{ $expense->transaction_date?->format('d/m/Y') }}
                                    ·
                                    {{ $expense->createdBy?->name ?? '-' }}
                                </p>
                            </div>
                            <span class="shrink-0 whitespace-nowrap font-semibold text-rose-700">
                                Rp {{ number_format((float) $expense->amount, 0, ',', '.') }}
                            </span>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-zinc-500">Belum ada pengeluaran.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-[#10233D]">Pengeluaran per Kategori</h2>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($expenseByCategory as $expenseCategory)
                        <div class="flex justify-between gap-4 py-3 text-sm">
                            <span class="font-medium text-zinc-700">{{ $expenseCategory->category }}</span>
                            <span class="shrink-0 font-semibold text-[#10233D]">
                                Rp {{ number_format((float) $expenseCategory->total, 0, ',', '.') }}
                            </span>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-zinc-500">Belum ada pengeluaran.</p>
                    @endforelse
                </div>
            </section>
        </div>
        <div class="grid gap-5 xl:grid-cols-3">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <h2 class="text-lg font-bold text-[#10233D]">Tren Booking 6 Bulan</h2>
                <p class="mt-1 text-sm text-slate-500">Jumlah booking aktif per bulan sesuai filter terpilih.</p>
                @if($monthlyBookingChart->sum('bookings') > 0)
                    <div role="img" aria-label="Grafik tren booking enam bulan" class="mt-5 grid h-52 grid-cols-6 items-end gap-2 border-b border-slate-200 px-1 sm:gap-4">
                        @foreach($monthlyBookingChart as $month)
                            @php($barHeight = $month['bookings'] > 0 ? max(4, ($month['bookings'] / $monthlyBookingChartMax) * 100) : 0)
                            <div class="flex h-full min-w-0 flex-col items-center justify-end gap-2">
                                <span class="text-xs font-bold text-[#10233D]">{{ $month['bookings'] }}</span>
                                <div class="flex h-36 w-full items-end justify-center">
                                    <div class="w-1/2 rounded-t-md bg-[#EB5120]" style="height: {{ $barHeight }}%" title="{{ $month['label'] }}: {{ $month['bookings'] }} booking"></div>
                                </div>
                                <span class="pb-2 text-center text-[10px] font-semibold text-slate-500 sm:text-xs">{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-5 flex h-52 items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-500">Belum ada booking aktif dalam enam bulan terakhir.</p>
                @endif
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-[#10233D]">Pipeline Booking</h2>
                <p class="mt-1 text-sm text-slate-500">Sebaran booking berdasarkan tahap proses.</p>
                @php($bookingStatusTotal = $bookingStatusChart->sum('value'))
                <div class="mt-5 space-y-4">
                    @foreach($bookingStatusChart as $status)
                        @php($statusPercent = $bookingStatusTotal > 0 ? $status['value'] / $bookingStatusTotal * 100 : 0)
                        <div>
                            <div class="mb-1.5 flex justify-between gap-3 text-sm">
                                <span class="font-semibold text-slate-600">{{ $status['label'] }}</span>
                                <span class="font-bold text-[#10233D]">{{ $status['value'] }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full" style="width: {{ $statusPercent }}%; background-color: {{ $status['color'] }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([['Customer',$stats['total_customers']],['Booking Aktif',$stats['active_bookings']],['Booking Proses',$stats['process_bookings']],['Booking Finish',$stats['finish_bookings']],['Kavling Available',$stats['available_lots']],['Kavling Blocked',$stats['blocked_lots']],['Kavling Terisi',$stats['booked_lots']],['Akad',$stats['akad_bookings']]] as [$label,$value])
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold">{{ $value }}</p></div>
            @endforeach
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900"><div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Booking Terbaru</h2><a href="{{ route('marketing.bookings.index') }}" class="text-sm text-blue-600" wire:navigate>Lihat semua</a></div><div class="mt-4 divide-y divide-zinc-200">@forelse($recentBookings as $booking)<div class="flex justify-between gap-4 py-3 text-sm"><div><p class="font-medium">{{ $booking->customer->name }} · {{ $booking->lot->block->name }}/{{ $booking->lot->lot_number }}</p><p class="text-zinc-500">{{ $booking->lot->block->property->name }} · {{ $booking->sales?->name ?? '-' }}</p></div><span class="font-medium">{{ ucfirst($booking->status) }}</span></div>@empty<p class="py-4 text-sm text-zinc-500">Belum ada booking.</p>@endforelse</div></section>
            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">Performa Sales</h2>
                <p class="mt-1 text-sm text-zinc-500">Sales dengan booking aktif terbanyak.</p>
                <div class="mt-5 space-y-4">
                    @forelse($salesPerformanceChart as $sales)
                        @php($salesBarWidth = $sales['bookings'] > 0 ? max(5, ($sales['bookings'] / $salesPerformanceChartMax) * 100) : 0)
                        <div>
                            <div class="mb-1.5 flex justify-between gap-3 text-sm">
                                <span class="truncate font-semibold text-[#10233D]">{{ $sales['name'] }}</span>
                                <span class="shrink-0 font-bold text-[#10233D]">{{ $sales['bookings'] }} booking</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-[#EB5120]" style="width: {{ $salesBarWidth }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-zinc-500">Belum ada data Sales.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-layouts::app>
