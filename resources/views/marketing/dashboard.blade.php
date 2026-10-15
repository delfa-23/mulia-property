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

        @if(session('status'))
            <p role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </p>
        @endif

        <section class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-[#10233D]">Ringkasan Keuangan</h2>
                    <p class="text-sm text-zinc-500">Harga kavling dan pengeluaran marketing sesuai filter.</p>
                </div>
                <a
                    href="{{ route('finance.index') }}"
                    class="inline-flex w-fit items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    wire:navigate
                >
                    Kelola Keuangan
                </a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <article class="min-w-0 rounded-xl border border-zinc-200 border-l-4 border-l-blue-500 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-500">Total Harga Kavling</p>
                    <p class="mt-3 wrap-break-word text-xl font-bold tabular-nums text-[#10233D]">
                        Rp {{ number_format((float) $financialStats['total_house_price'], 0, ',', '.') }}
                    </p>
                    <p class="mt-2 text-xs text-zinc-500">Nilai harga kavling pada filter terpilih</p>
                </article>

                <article class="min-w-0 rounded-xl border border-zinc-200 border-l-4 border-l-rose-500 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-500">Total Pengeluaran Marketing</p>
                    <p class="mt-3 wrap-break-word text-xl font-bold tabular-nums text-rose-700">
                        Rp {{ number_format((float) $financialStats['total_expense'], 0, ',', '.') }}
                    </p>
                    <p class="mt-2 text-xs text-zinc-500">Contoh: biaya promo dan pemasaran lainnya</p>
                </article>
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-[#10233D]">Saldo Kas &amp; Bank Marketing</h2>
                <p class="text-sm text-zinc-500">Perbarui saldo terakhir setiap rekening dan kas kecil.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($cashAccounts as $cashAccount)
                    <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <h3 class="font-semibold text-[#10233D]">{{ $cashAccount->name }}</h3>
                        <p class="mt-2 text-xl font-bold tabular-nums text-[#10233D]">
                            @if($cashAccount->balance === null)
                                Belum diisi
                            @else
                                Rp {{ number_format((float) $cashAccount->balance, 0, ',', '.') }}
                            @endif
                        </p>
                        <form method="POST" action="{{ route('marketing.cash-accounts.update', $cashAccount) }}" class="mt-4 flex gap-2">
                            @csrf
                            @method('PUT')
                            <label class="sr-only" for="balance-{{ $cashAccount->account_key }}">Saldo {{ $cashAccount->name }}</label>
                            <input
                                id="balance-{{ $cashAccount->account_key }}"
                                name="balance"
                                type="text"
                                inputmode="decimal"
                                data-currency-input
                                data-currency-min="0"
                                data-currency-step="0.01"
                                autocomplete="off"
                                required
                                value="{{ old('balance', $cashAccount->balance) }}"
                                placeholder="Masukkan saldo"
                                class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800"
                            >
                            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                Simpan
                            </button>
                        </form>
                        @error('balance')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </article>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-[#10233D]">Proyeksi Arus Kas Marketing</h2>
                <p class="text-sm text-zinc-500">Nilai bersifat kumulatif sejak hari ini. Penerimaan konservatif = confirmed; optimistis = confirmed + potential. Forecast ditampilkan terpisah.</p>
            </div>
            <div class="grid gap-4 lg:grid-cols-3">
                @foreach([7, 14, 30] as $days)
                    @php($forecast = $cashFlowForecasts[$days])
                    <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <h3 class="text-base font-semibold text-[#10233D]">{{ $days }} Hari</h3>
                        <div class="mt-4 border-b border-zinc-200 pb-4 dark:border-zinc-700">
                            <h4 class="text-sm font-semibold text-emerald-700">Rencana Income</h4>
                            <dl class="mt-2 space-y-2 text-sm">
                                <div class="flex justify-between gap-3"><dt class="text-zinc-500">Confirmed (konservatif)</dt><dd class="font-semibold tabular-nums">Rp {{ number_format($forecast['income_confirmed'], 0, ',', '.') }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-zinc-500">Potential</dt><dd class="font-semibold tabular-nums">Rp {{ number_format($forecast['income_potential'], 0, ',', '.') }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-zinc-500">Forecast</dt><dd class="font-semibold tabular-nums">Rp {{ number_format($forecast['income_forecast'], 0, ',', '.') }}</dd></div>
                                <div class="flex justify-between gap-3 border-t border-zinc-100 pt-2 font-semibold"><dt>Optimistis (Confirmed + Potential)</dt><dd class="tabular-nums">Rp {{ number_format($forecast['income_confirmed'] + $forecast['income_potential'], 0, ',', '.') }}</dd></div>
                            </dl>
                        </div>
                        <div class="pt-4">
                            <h4 class="text-sm font-semibold text-rose-700">Rencana Outcome</h4>
                            <dl class="mt-2 space-y-2 text-sm">
                                @foreach(['confirmed' => 'Confirmed', 'potential' => 'Potential', 'forecast' => 'Forecast'] as $status => $label)
                                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ $label }}</dt><dd class="font-semibold tabular-nums">Rp {{ number_format($forecast['outcome_'.$status], 0, ',', '.') }}</dd></div>
                                @endforeach
                            </dl>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('marketing.cash-flows.store') }}" class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                @csrf
                <div>
                    <h3 class="font-semibold text-[#10233D]">Tambah Rencana Income / Outcome</h3>
                    <p class="mt-1 text-sm text-zinc-500">Masukkan rencana penerimaan atau pengeluaran paling lambat sesuai tanggalnya.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="cash-flow-title" class="mb-1 block text-sm font-medium">Keterangan</label>
                        <input id="cash-flow-title" name="title" maxlength="150" required value="{{ old('title') }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                        @error('title')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="cash-flow-direction" class="mb-1 block text-sm font-medium">Jenis Arus Kas</label>
                        <select id="cash-flow-direction" name="direction" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="income" @selected(old('direction') === 'income')>Income</option>
                            <option value="outcome" @selected(old('direction') === 'outcome')>Outcome</option>
                        </select>
                        @error('direction')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="cash-flow-status" class="mb-1 block text-sm font-medium">Status</label>
                        <select id="cash-flow-status" name="status" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                            @foreach(['confirmed' => 'Confirmed', 'potential' => 'Potential', 'forecast' => 'Forecast'] as $status => $label)
                                <option value="{{ $status }}" @selected(old('status', 'confirmed') === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="cash-flow-amount" class="mb-1 block text-sm font-medium">Nominal (Rp)</label>
                        <input id="cash-flow-amount" name="amount" type="text" inputmode="decimal" data-currency-input data-currency-min="0.01" data-currency-step="0.01" autocomplete="off" required value="{{ old('amount') }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                        @error('amount')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="cash-flow-date" class="mb-1 block text-sm font-medium">Tanggal Rencana</label>
                        <input id="cash-flow-date" name="due_date" type="date" min="{{ today()->toDateString() }}" max="{{ today()->addDays(30)->toDateString() }}" required value="{{ old('due_date') }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                        @error('due_date')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="cash-flow-property" class="mb-1 block text-sm font-medium">Perumahan (opsional)</label>
                        <select id="cash-flow-property" name="property_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="">Semua / Umum</option>
                            @foreach($properties as $property)
                                <option value="{{ $property->id }}" @selected((string) old('property_id') === (string) $property->id)>{{ $property->name }}</option>
                            @endforeach
                        </select>
                        @error('property_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Simpan Rencana</button>
            </form>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="font-semibold text-[#10233D]">Rencana Arus Kas 30 Hari</h3>
                <div class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($cashFlowPlans as $cashFlow)
                        <div class="flex items-start justify-between gap-3 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium">{{ $cashFlow->title }}</p>
                                <p class="mt-1 text-xs text-zinc-500">
                                    {{ $cashFlow->direction === 'income' ? 'Income' : 'Outcome' }}
                                    · {{ ucfirst($cashFlow->status) }}
                                    · {{ $cashFlow->due_date->format('d/m/Y') }}
                                    · {{ $cashFlow->property?->name ?? 'Umum' }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="font-semibold tabular-nums {{ $cashFlow->direction === 'income' ? 'text-emerald-700' : 'text-rose-700' }}">
                                    Rp {{ number_format((float) $cashFlow->amount, 0, ',', '.') }}
                                </span>
                                <form method="POST" action="{{ route('marketing.cash-flows.destroy', $cashFlow) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-rose-600 hover:text-rose-800">Hapus</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-zinc-500">Belum ada rencana arus kas untuk 30 hari ke depan.</p>
                    @endforelse
                </div>
            </section>
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
