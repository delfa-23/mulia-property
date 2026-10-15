<x-layouts::app :title="$division === 'pembangunan' ? 'Keuangan Pembangunan' : 'Keuangan Marketing'">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">
                    {{ $division === 'pembangunan' ? 'Keuangan Pembangunan' : 'Keuangan Marketing' }}
                </h1>
                <p class="mt-1 text-sm text-zinc-500">
                    @if($division === 'marketing')
                        Harga kavling ditetapkan admin. Pengeluaran Marketing hanya dapat dicatat menggunakan kategori RAB Marketing yang dibuat admin.
                    @else
                        RAB pembangunan ditetapkan admin. Di sini tercatat anggaran, harga kavling, dan pengeluaran divisi.
                    @endif
                </p>
            </div>
            <a href="{{ route('finance.expense-transactions.create', ['division' => $division]) }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" wire:navigate>
                Tambah Pengeluaran
            </a>
        </div>

        @if(session('success'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('finance.index') }}" class="rounded-xl border border-zinc-200 bg-white p-4">
            @if(auth()->user()->isAdmin())
                <div class="mb-3">
                    <label for="division" class="mb-2 block text-sm font-medium">Divisi</label>
                    <select id="division" name="division" class="w-full rounded-lg border border-zinc-300 px-3 py-2 sm:w-64">
                        <option value="marketing" @selected($division === 'marketing')>Marketing</option>
                        <option value="pembangunan" @selected($division === 'pembangunan')>Pembangunan</option>
                    </select>
                </div>
            @endif
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto]">
                <div>
                    <label for="finance_property_id" class="mb-2 block text-sm font-medium">Perumahan</label>
                    <select id="finance_property_id" name="property_id" data-property-select class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                        <option value="">Semua Perumahan</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" @selected($propertyId == $property->id)>{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="finance_lot_id" class="mb-2 block text-sm font-medium">Blok / Kavling</label>
                    <select id="finance_lot_id" name="lot_id" data-lot-select class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                        <option value="">{{ $propertyId ? 'Semua blok dan kavling' : 'Pilih perumahan terlebih dahulu' }}</option>
                        @foreach($lots as $lot)
                            <option value="{{ $lot->id }}" data-property-id="{{ $lot->block->property_id }}" @selected($lotId == $lot->id)>
                                Blok {{ $lot->block->name }} / Kavling {{ $lot->lot_number }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white">Tampilkan</button>
                <a href="{{ route('finance.index', ['division' => $division]) }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-center text-sm font-medium">Reset</a>
            </div>
        </form>

        @if($division === 'marketing')
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Ringkasan keuangan marketing">
                <article class="rounded-xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950">
                    <p class="text-sm font-medium text-blue-800 dark:text-blue-200">Total RAB Marketing</p>
                    <p class="mt-2 text-3xl font-bold text-blue-950 dark:!text-white">Rp {{ number_format((float) $totalMarketingBudget, 0, ',', '.') }}</p>
                </article>
                <article class="rounded-xl border border-orange-200 bg-orange-50 p-5 dark:border-orange-900 dark:bg-orange-950">
                    <p class="text-sm font-medium text-orange-800 dark:text-orange-200">Total Pengeluaran Marketing</p>
                    <p class="mt-2 text-3xl font-bold text-orange-950 dark:!text-white">Rp {{ number_format((float) $totalExpense, 0, ',', '.') }}</p>
                </article>
                <article class="rounded-xl border {{ $totalMarketingDeviation < 0 ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950' : 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950' }} p-5">
                    <p class="text-sm font-medium {{ $totalMarketingDeviation < 0 ? 'text-red-800 dark:text-red-200' : 'text-emerald-800 dark:text-emerald-200' }}">Deviasi Anggaran (RAB - Pengeluaran)</p>
                    <p class="mt-2 text-3xl font-bold {{ $totalMarketingDeviation < 0 ? 'text-red-950 dark:!text-white' : 'text-emerald-950 dark:!text-white' }}">Rp {{ number_format((float) $totalMarketingDeviation, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs {{ $totalMarketingDeviation < 0 ? 'text-red-700 dark:text-red-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                        {{ $totalMarketingDeviation < 0 ? 'Pengeluaran melebihi RAB' : 'Sisa anggaran' }}
                    </p>
                </article>
            </section>
            <p class="text-xs text-zinc-500">Ringkasan pengeluaran dan deviasi menghitung semua transaksi sesuai filter. Tabel realisasi per RAB hanya menghitung transaksi yang sudah ditautkan ke RAB.</p>

            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-col gap-2 border-b border-zinc-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700">
                    <div>
                        <h2 class="text-lg font-semibold">RAB Marketing & Realisasi Pengeluaran</h2>
                        <p class="mt-1 text-sm text-zinc-500">Realisasi dihitung dari pengeluaran yang ditautkan ke setiap RAB Marketing.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-6 py-3">RAB / Kategori</th>
                                <th class="px-6 py-3">Perumahan / Sasaran</th>
                                <th class="px-6 py-3 text-right">Anggaran</th>
                                <th class="px-6 py-3 text-right">Realisasi</th>
                                <th class="px-6 py-3 text-right">Sisa Anggaran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($marketingBudgets as $marketingBudget)
                                @php
                                    $marketingBudgetAmount = (float) $marketingBudget->amount;
                                    $marketingRealizedAmount = (float) ($marketingBudget->marketing_expenses_sum ?? 0);
                                    $marketingRemainingAmount = $marketingBudgetAmount - $marketingRealizedAmount;
                                @endphp
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-medium">{{ $marketingBudget->name }}</div>
                                        <div class="mt-1 text-xs text-zinc-500">Kategori: {{ $marketingBudget->category }}</div>
                                        @if($marketingBudget->period_end?->isBefore(today()))
                                            <div role="alert" class="mt-2 inline-flex rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Periode RAB terlewati</div>
                                        @elseif($marketingBudget->period_end)
                                            <div class="mt-2 text-xs text-zinc-500">Batas: {{ $marketingBudget->period_end->format('d/m/Y') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>{{ $marketingBudget->property?->name ?? 'Semua perumahan' }}</div>
                                        <div class="mt-1 text-xs text-zinc-500">
                                            @if($marketingBudget->lot)
                                                Blok {{ $marketingBudget->lot->block?->name }} / Kavling {{ $marketingBudget->lot->lot_number }}
                                            @elseif($marketingBudget->facility)
                                                Fasilitas: {{ $marketingBudget->facility->name }}
                                            @else
                                                Project umum
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">Rp {{ number_format($marketingBudgetAmount, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right font-medium">Rp {{ number_format($marketingRealizedAmount, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right {{ $marketingRemainingAmount < 0 ? 'font-semibold text-red-600' : '' }}">
                                        Rp {{ number_format($marketingRemainingAmount, 0, ',', '.') }}
                                        @if($marketingRemainingAmount < 0)
                                            <span class="block text-xs">Melebihi anggaran</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-8 text-center text-zinc-500">Belum ada RAB Marketing dari admin untuk filter ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4">{{ $marketingBudgets->links() }}</div>
                <p class="border-t border-zinc-200 px-6 py-3 text-xs text-zinc-500 dark:border-zinc-700">Pengeluaran lama yang belum tertaut ke RAB tetap tampil pada daftar pengeluaran, tetapi tidak dihitung ke realisasi RAB tertentu.</p>
            </section>
        @endif

        @if($division === 'pembangunan')
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Ringkasan keuangan pembangunan">
                <article class="rounded-xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950">
                    <p class="text-sm font-medium text-blue-800 dark:text-blue-200">Total RAB Pembangunan</p>
                    <p class="mt-2 text-3xl font-bold text-blue-950 dark:!text-white">Rp {{ number_format((float) $totalBudget, 0, ',', '.') }}</p>
                </article>
                <article class="rounded-xl border border-orange-200 bg-orange-50 p-5 dark:border-orange-900 dark:bg-orange-950">
                    <p class="text-sm font-medium text-orange-800 dark:text-orange-200">Total Pengeluaran Pembangunan</p>
                    <p class="mt-2 text-3xl font-bold text-orange-950 dark:!text-white">Rp {{ number_format((float) $totalExpense, 0, ',', '.') }}</p>
                </article>
                <article class="rounded-xl border {{ $totalConstructionDeviation < 0 ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950' : 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950' }} p-5">
                    <p class="text-sm font-medium {{ $totalConstructionDeviation < 0 ? 'text-red-800 dark:text-red-200' : 'text-emerald-800 dark:text-emerald-200' }}">Deviasi Anggaran (RAB - Pengeluaran)</p>
                    <p class="mt-2 text-3xl font-bold {{ $totalConstructionDeviation < 0 ? 'text-red-950 dark:!text-white' : 'text-emerald-950 dark:!text-white' }}">Rp {{ number_format((float) $totalConstructionDeviation, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs {{ $totalConstructionDeviation < 0 ? 'text-red-700 dark:text-red-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                        {{ $totalConstructionDeviation < 0 ? 'Pengeluaran melebihi RAB' : 'Sisa anggaran' }}
                    </p>
                </article>
            </section>
            <p class="text-xs text-zinc-500">Ringkasan mengikuti filter perumahan dan kavling yang dipilih. Deviasi positif berarti sisa anggaran; nilai negatif berarti pengeluaran melebihi RAB.</p>

            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                <div class="flex flex-col gap-2 border-b border-zinc-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">RAB Pembangunan dari Admin</h2>
                        <p class="mt-1 text-sm text-zinc-500">Daftar RAB hanya-baca yang sudah ditetapkan admin.</p>
                    </div>
                    <p class="text-sm font-medium">Total RAB: Rp {{ number_format((float) $totalBudget, 0, ',', '.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50">
                            <tr>
                                <th class="px-6 py-3">Perumahan</th>
                                <th class="px-6 py-3">Lingkup</th>
                                <th class="px-6 py-3">Nama RAB</th>
                                <th class="px-6 py-3 text-right">Anggaran</th>
                                <th class="px-6 py-3 text-right">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @forelse($budgets as $budget)
                                <tr>
                                    <td class="px-6 py-4">{{ $budget->property?->name ?? '-' }}</td>
                                    <td class="px-6 py-4">
                                        @if($budget->lot)
                                            Blok {{ $budget->lot->block?->name }} / Kavling {{ $budget->lot->lot_number }}
                                        @elseif($budget->facility)
                                            Fasilitas: {{ $budget->facility->name }}
                                        @else
                                            Project
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium">{{ $budget->name }}</div>
                                        <div class="mt-1 text-xs text-zinc-500">Kategori: {{ $budget->category }}</div>
                                        @if($budget->period_end?->isBefore(today()))
                                            <div role="alert" class="mt-2 inline-flex rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                                                Deadline terlewati
                                            </div>
                                        @elseif($budget->period_end)
                                            <div class="mt-2 inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                Deadline: {{ $budget->period_end->format('d/m/Y') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right">Rp {{ number_format((float) $budget->amount, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <flux:modal.trigger :name="'budget-details-'.$budget->id">
                                            <flux:button type="button" variant="ghost" size="sm" class="border border-zinc-300">Detail</flux:button>
                                        </flux:modal.trigger>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-8 text-center text-zinc-500">Belum ada RAB pembangunan dari admin untuk filter ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4">{{ $budgets->links() }}</div>
            </section>

            @foreach($budgets as $budget)
                <flux:modal :name="'budget-details-'.$budget->id" class="w-[calc(100vw-2rem)] max-w-2xl !bg-white !text-[#10233D]">
                    <div class="space-y-5 bg-white text-[#10233D] sm:space-y-6">
                        <div class="border-b border-slate-200 pb-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#EB5120]">Keuangan Pembangunan</p>
                            <h2 class="mt-2 text-xl font-bold text-[#10233D] sm:text-2xl">Detail RAB</h2>
                            <p class="mt-1 break-words text-sm text-slate-600">{{ $budget->name }}</p>
                        </div>

                        @if($budget->period_end?->isBefore(today()))
                            <div role="alert" class="flex gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900">
                                <span class="mt-0.5 inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-red-100 text-sm font-bold text-red-700">!</span>
                                <div>
                                    <p class="font-semibold">Deadline RAB terlewati</p>
                                    <p class="mt-1 text-sm leading-relaxed">
                                        Periode RAB sudah berakhir pada {{ $budget->period_end->format('d/m/Y') }}. Mohon tinjau dan perbarui periode jika pekerjaan masih berlangsung.
                                    </p>
                                </div>
                            </div>
                        @endif

                        @if($budget->lot)
                            @php
                                $lotBudgetDetail = $lotBudgetDetails->get($budget->lot_id);
                                $housePrice = (float) $budget->lot->house_price;
                            @endphp
                            <section class="overflow-hidden rounded-xl border border-slate-200">
                                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                                    <h3 class="font-semibold text-[#10233D]">Harga Kavling + RAB</h3>
                                    <p class="mt-1 text-sm text-slate-600">Rincian seluruh anggaran admin yang terhubung ke kavling ini.</p>
                                </div>
                                <div class="space-y-4 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-sm text-slate-600">Harga rumah</span>
                                        <span class="font-semibold text-[#10233D]">Rp {{ number_format($housePrice, 0, ',', '.') }}</span>
                                    </div>

                                    <div>
                                        <h4 class="mb-2 text-sm font-semibold text-[#10233D]">Rincian RAB kavling</h4>
                                        <div class="divide-y divide-slate-200 rounded-lg border border-slate-200">
                                            @foreach($lotBudgetDetail['items'] ?? [] as $lotBudget)
                                                <div class="flex flex-wrap items-start justify-between gap-2 px-3 py-3">
                                                    <div>
                                                        <p class="font-medium text-[#10233D]">{{ $lotBudget->name }}</p>
                                                        <p class="mt-1 text-xs text-slate-600">Kategori: {{ $lotBudget->category }}</p>
                                                    </div>
                                                    <p class="font-semibold text-[#10233D]">Rp {{ number_format((float) $lotBudget->amount, 0, ',', '.') }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3">
                                        <span class="text-sm font-medium text-slate-700">Total RAB kavling</span>
                                        <span class="font-semibold text-[#10233D]">Rp {{ number_format($lotBudgetDetail['total'] ?? 0, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-orange-50 px-4 py-3">
                                        <span class="font-semibold text-orange-900">Harga rumah + total RAB</span>
                                        <span class="text-lg font-bold text-[#10233D]">Rp {{ number_format($housePrice + ($lotBudgetDetail['total'] ?? 0), 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </section>
                        @else
                            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm leading-relaxed text-blue-900">
                                RAB ini berlaku untuk project atau fasilitas, bukan satu kavling tertentu. Harga rumah dan total RAB per kavling tidak dijumlahkan karena anggaran ini belum dialokasikan ke kavling.
                            </div>
                        @endif

                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Jenis RAB</dt>
                                <dd class="mt-2 font-semibold text-[#10233D]">{{ ucfirst($budget->purpose) }}</dd>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Kategori</dt>
                                <dd class="mt-2 font-semibold text-[#10233D]">{{ $budget->category }}</dd>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Perumahan</dt>
                                <dd class="mt-2 break-words font-semibold text-[#10233D]">{{ $budget->property?->name ?? '-' }}</dd>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Lingkup</dt>
                                <dd class="mt-2 break-words font-semibold text-[#10233D]">
                                    @if($budget->lot)
                                        Blok {{ $budget->lot->block?->name }} / Kavling {{ $budget->lot->lot_number }}
                                    @elseif($budget->facility)
                                        Fasilitas: {{ $budget->facility->name }}
                                    @else
                                        Project umum
                                    @endif
                                </dd>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Periode Mulai</dt>
                                <dd class="mt-2 font-semibold text-[#10233D]">{{ $budget->period_start?->format('d/m/Y') ?? '-' }}</dd>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Periode Selesai</dt>
                                <dd class="mt-2 font-semibold text-[#10233D]">{{ $budget->period_end?->format('d/m/Y') ?? '-' }}</dd>
                            </div>
                            <div class="rounded-lg border border-orange-200 bg-orange-50 p-4 sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-orange-800">Nominal Anggaran</dt>
                                <dd class="mt-2 text-xl font-bold text-[#10233D] sm:text-2xl">Rp {{ number_format((float) $budget->amount, 0, ',', '.') }}</dd>
                            </div>
                            <div class="rounded-lg border border-slate-200 p-4 sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Catatan</dt>
                                <dd class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-[#10233D]">{{ $budget->notes ?: '-' }}</dd>
                            </div>
                            <div class="rounded-lg border border-slate-200 p-4 sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Dibuat Oleh</dt>
                                <dd class="mt-2 font-semibold text-[#10233D]">{{ $budget->createdBy?->name ?? '-' }}</dd>
                                <dd class="mt-1 text-sm text-slate-600">{{ $budget->created_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                            </div>
                        </dl>

                        <div class="flex justify-end border-t border-slate-200 pt-4">
                            <flux:modal.close>
                                <flux:button type="button" variant="primary" class="bg-[#EB5120]! text-white! hover:bg-[#D44215]!">Tutup</flux:button>
                            </flux:modal.close>
                        </div>
                    </div>
                </flux:modal>
            @endforeach
        @endif

        @if($division === 'pembangunan')
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">Total Harga Kavling Terfilter</p>
                    <p class="mt-2 text-xl font-semibold">Rp {{ number_format((float) $totalHousePrice, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">Total Pengeluaran {{ ucfirst($division) }}</p>
                    <p class="mt-2 text-xl font-semibold">Rp {{ number_format((float) $totalExpense, 0, ',', '.') }}</p>
                </div>
            </div>

            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                <div class="border-b border-zinc-200 px-6 py-4">
                    <h2 class="text-lg font-semibold">Daftar Harga Kavling</h2>
                    <p class="mt-1 text-sm text-zinc-500">Harga ditetapkan admin. Semua kavling ditampilkan; gunakan filter perumahan atau blok untuk mempersempit daftar.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50">
                            <tr><th class="px-6 py-3">Perumahan</th><th class="px-6 py-3">Blok / Kavling</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Harga</th></tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @forelse($housePrices as $lot)
                                <tr>
                                    <td class="px-6 py-4">{{ $lot->block->property->name }}</td>
                                    <td class="px-6 py-4">{{ $lot->block->name }} / {{ $lot->lot_number }}</td>
                                    <td class="px-6 py-4">{{ ucfirst($lot->status) }}</td>
                                    <td class="px-6 py-4 text-right">Rp {{ number_format((float) $lot->house_price, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-8 text-center text-zinc-500">{{ $propertyId ? 'Belum ada kavling pada filter ini.' : 'Belum ada kavling yang ditambahkan admin.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4">{{ $housePrices->links() }}</div>
            </section>
        @endif

        @if($division === 'pembangunan')
            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                    <h2 class="text-lg font-semibold">Rincian RAB & Pengeluaran per Kategori</h2>
                    <p class="mt-1 text-sm text-zinc-500">Total RAB pembangunan, pengeluaran, dan deviasi untuk setiap kategori pada project sesuai filter.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-6 py-3">Project / Kategori</th>
                                <th class="px-6 py-3 text-right">RAB</th>
                                <th class="px-6 py-3 text-right">Pengeluaran</th>
                                <th class="px-6 py-3 text-right">Deviasi (RAB - Pengeluaran)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($constructionCategorySummaries as $categorySummary)
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-medium">{{ $categorySummary['property']?->name ?? 'Project tidak ditentukan' }}</div>
                                        <div class="mt-1 text-xs text-zinc-500">{{ $categorySummary['category'] }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-right">Rp {{ number_format($categorySummary['budget'], 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right">Rp {{ number_format($categorySummary['expense'], 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-right font-semibold {{ $categorySummary['deviation'] < 0 ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                        Rp {{ number_format($categorySummary['deviation'], 0, ',', '.') }}
                                        <span class="block text-xs font-normal">{{ $categorySummary['deviation'] < 0 ? 'Melebihi RAB' : 'Sisa anggaran' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-zinc-500">Belum ada RAB atau pengeluaran pembangunan per kategori pada filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
            <div class="border-b border-zinc-200 px-6 py-4">
                <h2 class="text-lg font-semibold">Pengeluaran {{ ucfirst($division) }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50">
                        <tr><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Project</th><th class="px-6 py-3">Keterangan / Kategori</th><th class="px-6 py-3">PIC</th><th class="px-6 py-3 text-right">Nominal</th><th class="px-6 py-3 text-right">Aksi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse($expenses as $expense)
                            <tr>
                                <td class="px-6 py-4">{{ $expense->transaction_date?->format('d/m/Y') }}</td>
                                <td class="px-6 py-4">{{ $expense->property?->name ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-medium">{{ $expense->category }}</div>
                                    <div class="text-xs text-zinc-500">{{ $expense->description ?: ($expense->lot ? 'Blok '.$expense->lot->block?->name.' / Kavling '.$expense->lot->lot_number : ($expense->facility?->name ?? '-')) }}</div>
                                </td>
                                <td class="px-6 py-4">{{ $expense->createdBy?->name ?? '-' }}</td>
                                <td class="px-6 py-4 text-right">Rp {{ number_format((float) $expense->amount, 0, ',', '.') }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('finance.expense-transactions.edit', $expense) }}" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs" wire:navigate>Edit</a>
                                        <form method="POST" action="{{ route('finance.expense-transactions.destroy', $expense) }}" onsubmit="return confirm('Hapus pengeluaran ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-600">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-8 text-center text-zinc-500">Belum ada pengeluaran pada filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $expenses->links() }}</div>
        </section>
    </div>
</x-layouts::app>
