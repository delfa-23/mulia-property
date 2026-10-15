<x-layouts::app :title="__('Saldo Bank & Rencana Keuangan')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-[#10233D]">Administrasi &amp; Keuangan</h1>
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
                            <div class="rounded-lg bg-emerald-50 p-3 dark:bg-emerald-950/40">
                                <dt class="text-xs font-medium text-emerald-800 dark:text-emerald-200">Rencana pemasukan</dt>
                                <dd class="mt-1 text-lg font-semibold text-emerald-950 dark:text-emerald-100">Rp {{ number_format($forecast['income_confirmed'] + $forecast['income_potential'] + $forecast['income_forecast'], 0, ',', '.') }}</dd>
                                <p class="mt-1 text-xs text-emerald-800 dark:text-emerald-200">Confirmed {{ number_format($forecast['income_confirmed'], 0, ',', '.') }} · Potential {{ number_format($forecast['income_potential'], 0, ',', '.') }} · Forecast {{ number_format($forecast['income_forecast'], 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-lg bg-rose-50 p-3 dark:bg-rose-950/40">
                                <dt class="text-xs font-medium text-rose-800 dark:text-rose-200">Rencana pengeluaran</dt>
                                <dd class="mt-1 text-lg font-semibold text-rose-950 dark:text-rose-100">Rp {{ number_format($forecast['outcome_confirmed'] + $forecast['outcome_potential'] + $forecast['outcome_forecast'], 0, ',', '.') }}</dd>
                                <p class="mt-1 text-xs text-rose-800 dark:text-rose-200">Confirmed {{ number_format($forecast['outcome_confirmed'], 0, ',', '.') }} · Potential {{ number_format($forecast['outcome_potential'], 0, ',', '.') }} · Forecast {{ number_format($forecast['outcome_forecast'], 0, ',', '.') }}</p>
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

            <form
                method="POST"
                action="{{ route('keuangan.cash-flows.store') }}"
                x-data="{ weekNumber: @js($selectedWeek), dateBounds: @js($weekDateBounds) }"
                class="grid gap-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-4 dark:border-zinc-700 dark:bg-zinc-900"
            >
                @csrf
                <h3 class="text-base font-semibold text-[#10233D] sm:col-span-2 xl:col-span-4 dark:text-white">Tambah Rencana</h3>
                <div>
                    <label for="cash-flow-direction" class="mb-1 block text-sm font-medium">Jenis</label>
                    <select id="cash-flow-direction" name="direction" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                        <option value="income" @selected(old('direction') === 'income')>Pemasukan</option>
                        <option value="outcome" @selected(old('direction') === 'outcome')>Pengeluaran</option>
                    </select>
                </div>
                <div>
                    <label for="cash-flow-week" class="mb-1 block text-sm font-medium">Minggu</label>
                    <select id="cash-flow-week" name="week_number" x-model="weekNumber" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                        @foreach($weekOptions as $number => $week)
                            <option value="{{ $number }}" @selected((string) old('week_number', 1) === (string) $number)>Minggu ke-{{ $number }} ({{ $week['label'] }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="cash-flow-due-date" class="mb-1 block text-sm font-medium">Tanggal</label>
                    <input id="cash-flow-due-date" name="due_date" type="date" value="{{ old('due_date') }}" :min="dateBounds[weekNumber].min" :max="dateBounds[weekNumber].max" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                </div>
                <div>
                    <label for="cash-flow-amount" class="mb-1 block text-sm font-medium">Nominal (Rp)</label>
                    <input id="cash-flow-amount" name="amount" type="text" inputmode="decimal" data-currency-input data-currency-min="0.01" data-currency-step="0.01" autocomplete="off" value="{{ old('amount') }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                </div>
                <div class="sm:col-span-2">
                    <label for="cash-flow-title" class="mb-1 block text-sm font-medium">Keterangan</label>
                    <input id="cash-flow-title" name="title" maxlength="150" value="{{ old('title') }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                </div>
                <div>
                    <label for="cash-flow-status" class="mb-1 block text-sm font-medium">Kepastian</label>
                    <select id="cash-flow-status" name="status" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                        @foreach(['confirmed' => 'Confirmed', 'potential' => 'Potential', 'forecast' => 'Forecast'] as $status => $label)
                            <option value="{{ $status }}" @selected(old('status', 'confirmed') === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="cash-flow-property" class="mb-1 block text-sm font-medium">Perumahan (opsional)</label>
                    <select id="cash-flow-property" name="property_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                        <option value="">Tidak terkait perumahan</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" @selected(old('property_id') == $property->id)>{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end xl:col-span-4">
                    <button type="submit" class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">Simpan rencana</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts::app>
