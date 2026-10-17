<x-layouts::app :title="__('Administrasi & Keuangan Finance')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-[#10233D]">Divisi Finance - Administrasi &amp; Keuangan</h1>
            <p class="mt-1 text-sm text-zinc-500">Kelola saldo rekening dan proyeksi pemasukan/pengeluaran per minggu.</p>
        </div>

        @if(session('success'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-[#10233D]">Saldo Bank &amp; Kas</h2>
                    <p class="text-sm text-zinc-500">Tambahkan bank atau rekening apa pun; saldo dapat diperbarui kapan saja.</p>
                </div>
                <form method="POST" action="{{ route('keuangan.cash-accounts.store') }}" class="grid gap-2 sm:grid-cols-[minmax(12rem,1fr)_minmax(10rem,1fr)_auto]">
                    @csrf
                    <div>
                        <label for="cash-account-name" class="sr-only">Nama bank atau kas</label>
                        <input id="cash-account-name" name="name" value="{{ old('name') }}" maxlength="255" required placeholder="Nama bank / rekening" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    </div>
                    <div>
                        <label for="cash-account-balance" class="sr-only">Saldo awal</label>
                        <input id="cash-account-balance" name="balance" type="text" inputmode="decimal" data-currency-input data-currency-min="0" data-currency-step="0.01" autocomplete="off" placeholder="Saldo awal (opsional)" value="{{ old('balance') }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    </div>
                    <button type="submit" class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600 focus:outline-none focus:ring-4 focus:ring-orange-300 dark:focus:ring-orange-800">Tambah rekening</button>
                </form>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse($cashAccounts as $cashAccount)
                    <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <h3 class="font-semibold text-[#10233D] dark:text-white">{{ $cashAccount->name }}</h3>
                        <p class="mt-2 text-xl font-bold tabular-nums text-[#10233D] dark:text-white">
                            @if($cashAccount->balance === null)
                                Belum diisi
                            @else
                                Rp {{ number_format((float) $cashAccount->balance, 0, ',', '.') }}
                            @endif
                        </p>
                        <form method="POST" action="{{ route('keuangan.cash-accounts.update', $cashAccount) }}" class="mt-4 flex gap-2">
                            @csrf
                            @method('PUT')
                            <label class="sr-only" for="balance-{{ $cashAccount->id }}">Saldo {{ $cashAccount->name }}</label>
                            <input id="balance-{{ $cashAccount->id }}" name="balance" type="text" inputmode="decimal" data-currency-input data-currency-min="0" data-currency-step="0.01" autocomplete="off" required value="{{ $cashAccount->balance }}" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <button type="submit" class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-medium text-white transition hover:bg-blue-800">Simpan</button>
                        </form>
                    </article>
                @empty
                    <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-sm text-zinc-500 sm:col-span-2 xl:col-span-4">Belum ada rekening yang dicatat.</p>
                @endforelse
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-[#10233D]">Rencana Pemasukan &amp; Pengeluaran</h2>
                <p class="text-sm text-zinc-500">Setiap periode adalah minggu kalender Senin–Minggu. Minggu 1 dimulai dari minggu berjalan.</p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach($cashFlowForecasts as $weekNumber => $forecast)
                    <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="font-semibold text-[#10233D] dark:text-white">Minggu ke-{{ $weekNumber }}</h3>
                                <p class="text-sm text-zinc-500">{{ $forecast['label'] }}</p>
                            </div>
                            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-950 dark:text-blue-200">
                                Pemasukan Rp {{ number_format($forecast['income_confirmed'] + $forecast['income_potential'] + $forecast['income_forecast'], 0, ',', '.') }}
                            </span>
                        </div>
                        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg bg-emerald-200 p-3">
                                <dt class="text-xs font-medium text-emerald-950">Rencana pemasukan</dt>
                                <dd class="mt-1 text-lg font-semibold text-emerald-950">Rp {{ number_format($forecast['income_confirmed'] + $forecast['income_potential'] + $forecast['income_forecast'], 0, ',', '.') }}</dd>
                                <p class="mt-1 text-xs text-emerald-950">Confirmed {{ number_format($forecast['income_confirmed'], 0, ',', '.') }} · Potential {{ number_format($forecast['income_potential'], 0, ',', '.') }} · Forecast {{ number_format($forecast['income_forecast'], 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-lg bg-rose-200 p-3">
                                <dt class="text-xs font-medium text-rose-950">Rencana pengeluaran</dt>
                                <dd class="mt-1 text-lg font-semibold text-rose-950">Rp {{ number_format($forecast['outcome_confirmed'] + $forecast['outcome_potential'] + $forecast['outcome_forecast'], 0, ',', '.') }}</dd>
                                <p class="mt-1 text-xs text-rose-950">Confirmed {{ number_format($forecast['outcome_confirmed'], 0, ',', '.') }} · Potential {{ number_format($forecast['outcome_potential'], 0, ',', '.') }} · Forecast {{ number_format($forecast['outcome_forecast'], 0, ',', '.') }}</p>
                            </div>
                        </dl>

                        <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($forecast['plans'] as $plan)
                                <div class="flex items-start justify-between gap-3 py-3">
                                    <div class="min-w-0">
                                        <p class="font-medium">{{ $plan->title }}</p>
                                        <p class="mt-1 text-xs text-zinc-500">{{ $plan->due_date->format('d/m/Y') }} · {{ $plan->direction === 'income' ? 'Pemasukan' : 'Pengeluaran' }} · {{ ucfirst($plan->status) }}{{ $plan->property ? ' · '.$plan->property->name : '' }}</p>
                                        <p class="mt-1 text-sm font-semibold tabular-nums">Rp {{ number_format((float) $plan->amount, 0, ',', '.') }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('keuangan.cash-flows.destroy', $plan) }}" onsubmit="return confirm('Hapus rencana arus kas ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs font-medium text-rose-700 hover:underline dark:text-rose-300">Hapus</button>
                                    </form>
                                </div>
                            @empty
                                <p class="py-4 text-sm text-zinc-500">Belum ada rencana pada minggu ini.</p>
                            @endforelse
                        </div>
                    </article>
                @endforeach
            </div>

            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-col gap-4 border-b border-zinc-200 px-5 py-4 sm:flex-row sm:items-end sm:justify-between dark:border-zinc-700">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-semibold text-[#10233D] dark:text-white">Riwayat Bulanan</h3>
                            @if($historyIsArchived)
                                <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">Diarsipkan otomatis</span>
                            @elseif($historyIsFuture)
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">Rencana mendatang</span>
                            @else
                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">Bulan berjalan</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-zinc-500">Ringkasan {{ $historyMonthLabels[$selectedHistoryMonth] }}. Pilih bulan untuk melihat seluruh rencana beserta detailnya.</p>
                    </div>
                    <form method="GET" action="{{ route('keuangan.cash-position.index') }}" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-end">
                        <div class="min-w-52 space-y-1.5">
                            <label for="history-month" class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Bulan riwayat</label>
                            <select id="history-month" name="history_month" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                @foreach($historyMonthLabels as $month => $label)
                                    <option value="{{ $month }}" @selected($selectedHistoryMonth === $month)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="rounded-lg bg-[#10233D] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1e3a5f]">Lihat bulan</button>
                    </form>
                </div>

                <div class="grid gap-3 border-b border-zinc-200 p-5 sm:grid-cols-2 xl:grid-cols-3 dark:border-zinc-700">
                    <article class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-sm font-medium text-emerald-900">Total pemasukan</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-emerald-950">Rp {{ number_format((float) $historySummary->income_total, 0, ',', '.') }}</p>
                    </article>
                    <article class="rounded-lg border border-rose-200 bg-rose-50 p-4">
                        <p class="text-sm font-medium text-rose-900">Total pengeluaran</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-rose-950">Rp {{ number_format((float) $historySummary->outcome_total, 0, ',', '.') }}</p>
                    </article>
                    <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 sm:col-span-2 xl:col-span-1">
                        <p class="text-sm font-medium text-zinc-700">Selisih bulan</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-[#10233D]">Rp {{ number_format((float) $historySummary->income_total - (float) $historySummary->outcome_total, 0, ',', '.') }}</p>
                    </article>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead>
                            <tr>
                                <th class="px-5 py-3">Tanggal</th>
                                <th class="px-5 py-3">Rencana</th>
                                <th class="px-5 py-3">Jenis</th>
                                <th class="px-5 py-3">Kepastian</th>
                                <th class="px-5 py-3 text-right">Nominal</th>
                                <th class="px-5 py-3">Dibuat oleh</th>
                                <th class="px-5 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($cashFlowHistory as $plan)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-5 py-4 font-medium text-zinc-700">{{ $plan->due_date->format('d/m/Y') }}</td>
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-[#10233D]">{{ $plan->title }}</p>
                                        <p class="mt-1 text-xs text-zinc-500">{{ $plan->property?->name ?? 'Tidak terkait perumahan' }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $plan->direction === 'income' ? 'bg-emerald-100 text-emerald-900' : 'bg-rose-100 text-rose-900' }}">
                                            {{ $plan->direction === 'income' ? 'Pemasukan' : 'Pengeluaran' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 capitalize text-zinc-700">{{ $plan->status }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold tabular-nums text-[#10233D]">Rp {{ number_format((float) $plan->amount, 0, ',', '.') }}</td>
                                    <td class="px-5 py-4 text-zinc-600">{{ $plan->createdBy?->name ?? 'Pengguna dihapus' }}</td>
                                    <td class="px-5 py-4 text-right">
                                        <form method="POST" action="{{ route('keuangan.cash-flows.destroy', $plan) }}" onsubmit="return confirm('Hapus rencana arus kas ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center">
                                        <p class="font-medium text-zinc-700">Belum ada rencana pada {{ $historyMonthLabels[$selectedHistoryMonth] }}.</p>
                                        <p class="mt-1 text-sm text-zinc-500">Rencana yang sudah lewat akan tersimpan di sini sebagai arsip bulanan.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($cashFlowHistory->hasPages())
                    <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        {{ $cashFlowHistory->links() }}
                    </div>
                @endif
            </section>

            <form
                method="POST"
                action="{{ route('keuangan.cash-flows.store') }}"
                x-data="{ weekNumber: @js($selectedWeek), dateBounds: @js($weekDateBounds) }"
                class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
            >
                @csrf
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="text-base font-semibold text-[#10233D] dark:text-white">Tambah Rencana</h3>
                    <p class="mt-1 text-sm text-zinc-500">Catat pemasukan atau pengeluaran pada minggu yang sesuai.</p>
                </div>

                <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-6">
                    <div class="space-y-1.5 xl:col-span-2">
                        <label for="cash-flow-direction" class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Jenis</label>
                        <select id="cash-flow-direction" name="direction" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <option value="income" @selected(old('direction') === 'income')>Pemasukan</option>
                            <option value="outcome" @selected(old('direction') === 'outcome')>Pengeluaran</option>
                        </select>
                    </div>
                    <div class="space-y-1.5 sm:col-span-2 xl:col-span-4">
                        <label for="cash-flow-title" class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Keterangan</label>
                        <input id="cash-flow-title" name="title" maxlength="150" value="{{ old('title') }}" required placeholder="Contoh: Pembayaran material proyek" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500">
                    </div>
                    <div class="space-y-1.5 xl:col-span-2">
                        <label for="cash-flow-amount" class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Nominal (Rp)</label>
                        <input id="cash-flow-amount" name="amount" type="text" inputmode="decimal" data-currency-input data-currency-min="0.01" data-currency-step="0.01" autocomplete="off" value="{{ old('amount') }}" required placeholder="Masukkan nominal" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500">
                    </div>
                    <div class="space-y-1.5 xl:col-span-2">
                        <label for="cash-flow-status" class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Kepastian</label>
                        <select id="cash-flow-status" name="status" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            @foreach(['confirmed' => 'Confirmed', 'potential' => 'Potential', 'forecast' => 'Forecast'] as $status => $label)
                                <option value="{{ $status }}" @selected(old('status', 'confirmed') === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5 xl:col-span-2">
                        <label for="cash-flow-week" class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Minggu</label>
                        <select id="cash-flow-week" name="week_number" x-model="weekNumber" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            @foreach($weekOptions as $number => $week)
                                <option value="{{ $number }}" @selected((string) old('week_number', 1) === (string) $number)>Minggu ke-{{ $number }} ({{ $week['label'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5 xl:col-span-2">
                        <label for="cash-flow-due-date" class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Tanggal</label>
                        <input id="cash-flow-due-date" name="due_date" type="date" value="{{ old('due_date') }}" :min="dateBounds[weekNumber].min" :max="dateBounds[weekNumber].max" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                    </div>
                    <div class="space-y-1.5 sm:col-span-2 xl:col-span-6">
                        <label for="cash-flow-property" class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Perumahan <span class="font-normal text-zinc-500">(opsional)</span></label>
                        <select id="cash-flow-property" name="property_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <option value="">Tidak terkait perumahan</option>
                            @foreach($properties as $property)
                                <option value="{{ $property->id }}" @selected(old('property_id') == $property->id)>{{ $property->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex justify-end border-t border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <button type="submit" class="w-full rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 sm:w-auto dark:focus:ring-offset-zinc-900">Simpan rencana</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts::app>
