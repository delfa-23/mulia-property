<x-layouts::app :title="__('Pembayaran Booking')">
    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#c2410c]">Marketing · Transaksi</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-[#10233d] sm:text-3xl">Pembayaran Booking</h1>
                <p class="mt-1 text-sm text-[#526174]">
                    Pantau tagihan customer dan kelola pembayaran booking melalui Midtrans.
                </p>
            </div>
            <span @class([
                'inline-flex w-fit items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold',
                'border-emerald-200 bg-emerald-50 text-emerald-800' => $midtransConfigured,
                'border-amber-300 bg-amber-50 text-amber-900' => ! $midtransConfigured,
            ])>
                <span @class([
                    'size-2 rounded-full',
                    'bg-emerald-500' => $midtransConfigured,
                    'bg-amber-500' => ! $midtransConfigured,
                ])></span>
                {{ $midtransConfigured ? 'Midtrans siap digunakan' : 'Midtrans belum dikonfigurasi' }}
            </span>
        </header>

        @if($pendingManualPayments->isNotEmpty())
            <section class="overflow-hidden rounded-xl border border-amber-400 bg-white shadow-lg dark:border-amber-700 dark:bg-zinc-900" aria-labelledby="pending-payment-heading">
                <div class="flex flex-wrap items-start justify-between gap-3 border-l-4 border-amber-500 bg-[#FFF2CC] px-5 py-4 dark:bg-amber-950/30">
                    <div>
                        <h2 id="pending-payment-heading" class="font-bold text-amber-950 dark:text-amber-200">Pembayaran Manual Menunggu Persetujuan</h2>
                        <p class="mt-1 text-sm font-bold text-amber-950 dark:text-amber-100">{{ $pendingManualPayments->count() }} pembayaran perlu diperiksa admin.</p>
                    </div>
                </div>
                <div class="space-y-4 p-5">
                    @foreach($pendingManualPayments as $payment)
                        <article class="rounded-xl border border-slate-300 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <p class="font-bold text-[#10233D] dark:text-white">{{ $payment->booking->customer->name ?? '-' }}</p>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $payment->booking->lot->block->property->name ?? '-' }} · Kavling {{ $payment->booking->lot->lot_number }}
                                    </p>
                                    <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-200">
                                        {{ $payment->paymentSchedule?->title ?? match ($payment->stage) {
                                            'dp', 'bank_settlement', 'booking' => 'Pembayaran Booking',
                                            'booking_balance' => 'Sisa Pembayaran Booking',
                                            'akad_fee' => 'Biaya Akad',
                                            default => \Illuminate\Support\Str::headline($payment->stage),
                                        } }}
                                        · Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                        · {{ $payment->paid_at->format('d/m/Y H:i') }}
                                    </p>
                                    @if($payment->notes)<p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Keterangan: {{ $payment->notes }}</p>@endif
                                    @if($payment->proof_path)
                                        <a href="{{ Storage::url($payment->proof_path) }}" target="_blank" class="mt-2 inline-flex text-sm font-semibold text-blue-700 underline hover:text-blue-900">Lihat bukti pembayaran</a>
                                    @endif

                                    @if($pendingUnpaidReasons->isNotEmpty())
                                        <section class="overflow-hidden rounded-xl border border-blue-400 bg-white shadow-lg dark:border-blue-700 dark:bg-zinc-900">
                                            <div class="border-l-4 border-blue-600 bg-blue-50 px-5 py-4 dark:bg-blue-950/40">
                                                <h2 class="font-bold text-blue-950 dark:text-blue-100">Alasan Pembayaran Belum Lunas Menunggu Persetujuan</h2>
                                                                                                    <p class="mt-1 rounded bg-blue-100 px-2 py-1 text-sm font-bold text-slate-950 dark:bg-blue-950 dark:text-white">{{ $pendingUnpaidReasons->count() }} alasan perlu diperiksa admin.</p>
                                            </div>
                                            <div class="space-y-3 p-5">
                                                @foreach($pendingUnpaidReasons as $reasonBooking)
                                                    <article class="grid gap-4 rounded-xl border border-slate-300 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_auto] dark:border-zinc-700 dark:bg-zinc-900">
                                                        <div>
                                                            <p class="font-bold text-slate-950 dark:text-white">{{ $reasonBooking->customer->name }}</p>
                                                            <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">{{ $reasonBooking->lot->block->property->name }} · Kavling {{ $reasonBooking->lot->lot_number }}</p>
                                                            <p class="mt-2 rounded-lg bg-slate-100 p-3 text-sm text-slate-950 dark:bg-zinc-800 dark:text-white">{{ $reasonBooking->unpaid_payment_reason }}</p>
                                                        </div>
                                                        <div class="flex flex-col justify-center gap-2 sm:flex-row md:flex-col">
                                                            <form method="POST" action="{{ route('marketing.payments.unpaid-reason.approve', $reasonBooking) }}">
                                                                @csrf
                                                                <button type="submit" class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Setujui Alasan</button>
                                                            </form>
                                                            <form method="POST" action="{{ route('marketing.payments.unpaid-reason.reject', $reasonBooking) }}">
                                                                @csrf
                                                                <button type="submit" class="min-h-11 w-full rounded-lg border-2 border-red-950 bg-red-700 px-4 py-2.5 text-sm font-black text-white shadow-sm hover:bg-red-800 dark:border-red-300">TOLAK ALASAN</button>
                                                            </form>
                                                        </div>
                                                    </article>
                                                @endforeach
                                            </div>
                                        </section>
                                    @endif
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2 lg:min-w-80">
                                    <form method="POST" action="{{ route('admin.payments.approve', $payment) }}">
                                        @csrf
                                        <button type="submit" class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">Setujui</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="sm:col-span-2 grid gap-2 rounded-xl border border-red-400 bg-red-100 p-3 text-slate-900 shadow-sm dark:border-red-800 dark:bg-red-950/40 dark:text-white">
                                        @csrf
                                        <label for="rejection-reason-{{ $payment->id }}" class="text-sm font-bold text-slate-950 dark:text-white">Alasan penolakan</label>
                                        <textarea id="rejection-reason-{{ $payment->id }}" name="rejection_reason" required maxlength="500" rows="2" class="w-full rounded-lg border-red-400 bg-white px-3 py-2 text-sm text-slate-950 placeholder:text-slate-400 focus:border-red-600 focus:ring-red-600 dark:border-red-700 dark:bg-zinc-950 dark:text-white"></textarea>
                                        <button type="submit" class="min-h-11 rounded-lg border border-red-800 bg-red-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-red-800 dark:bg-red-600 dark:hover:bg-red-500">Tolak Pembayaran</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if(session('success'))
            <div role="status" class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-[#14532d] shadow-sm">
                <span class="mt-0.5 font-bold text-emerald-700" aria-hidden="true">✓</span>
                <p>{{ session('success') }}</p>
            </div>
        @endif
        @if(session('error'))
            <div role="alert" class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-[#9f1239] shadow-sm">
                <span class="mt-0.5 font-bold text-rose-700" aria-hidden="true">!</span>
                <p>{{ session('error') }}</p>
            </div>
        @endif
        @if(! $midtransConfigured)
            <div role="status" class="flex items-start gap-3 rounded-xl border border-amber-300 bg-[#fff7ed] px-4 py-4 text-sm text-[#7c2d12] shadow-sm">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-amber-200 text-sm font-bold text-[#7c2d12]" aria-hidden="true">!</span>
                <div class="space-y-1">
                    <p class="font-semibold">Checkout Midtrans belum dapat digunakan</p>
                    <p class="leading-6 text-[#7c2d12]">
                        Tim teknis perlu mengatur <code class="rounded bg-white px-1.5 py-0.5 font-mono text-xs font-semibold text-[#7c2d12] ring-1 ring-amber-300">MIDTRANS_SERVER_KEY</code> sebelum pembayaran online dapat dimulai.
                    </p>
                </div>
            </div>
        @endif
        <div class="flex items-start gap-3 rounded-xl border border-[#cbd5e1] bg-white px-4 py-4 text-sm text-[#334155] shadow-sm">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-[#fff4ef] font-bold text-[#c2410c]" aria-hidden="true">i</span>
            <p class="leading-6">
                Setelah memilih <strong class="font-semibold text-[#10233d]">Bayar via Midtrans</strong>, customer memilih metode yang tersedia—seperti Virtual Account, QRIS, atau e-wallet—di halaman pembayaran Midtrans.
            </p>
        </div>

        <form method="GET" action="{{ route('marketing.payments.index') }}" class="space-y-4 rounded-xl border border-[#dbe2ea] bg-white p-4 shadow-sm sm:p-5">
            <input type="hidden" name="archive" value="{{ $archive }}">
            <div>
                <h2 class="text-base font-semibold text-[#10233d]">Cari pembayaran</h2>
                <p class="mt-1 text-sm text-[#64748b]">Persempit daftar berdasarkan perumahan, status booking, atau customer.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1.4fr_auto] xl:items-end">
                <div>
                    <label for="payment-property" class="mb-2 block text-sm font-semibold text-[#334155]">Perumahan</label>
                    <select id="payment-property" name="property_id" class="w-full rounded-lg border border-[#cbd5e1] bg-white px-3 py-2.5 text-sm text-[#10233d] focus:border-[#eb5120] focus:outline-none focus:ring-2 focus:ring-[#eb5120]/20">
                        <option value="">Semua perumahan</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" @selected(($filters['property_id'] ?? '') == $property->id)>{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="payment-booking-status" class="mb-2 block text-sm font-semibold text-[#334155]">Status booking</label>
                    <select id="payment-booking-status" name="status" class="w-full rounded-lg border border-[#cbd5e1] bg-white px-3 py-2.5 text-sm text-[#10233d] focus:border-[#eb5120] focus:outline-none focus:ring-2 focus:ring-[#eb5120]/20">
                        <option value="">Semua status</option>
                        @foreach(['booking' => 'Booking', 'process' => 'Proses', 'akad' => 'Akad', 'finish' => 'Serah Terima Rumah', 'cancelled' => 'Dibatalkan'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="payment-search" class="mb-2 block text-sm font-semibold text-[#334155]">Customer / kavling</label>
                    <input id="payment-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama, telepon, blok, atau nomor kavling" class="w-full rounded-lg border border-[#cbd5e1] bg-white px-3 py-2.5 text-sm text-[#10233d] placeholder:text-[#94a3b8] focus:border-[#eb5120] focus:outline-none focus:ring-2 focus:ring-[#eb5120]/20">
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#eb5120] px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#d44215] focus:outline-none focus:ring-2 focus:ring-[#eb5120]/30 focus:ring-offset-2">Terapkan filter</button>
                    <a href="{{ route('marketing.payments.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[#cbd5e1] px-4 text-sm font-semibold text-[#475569] transition hover:bg-[#f8fafc] hover:text-[#10233d]">Reset</a>
                </div>
            </div>
        </form>

        <nav aria-label="Daftar pembayaran" class="flex flex-wrap gap-2">
            <a
                href="{{ route('marketing.payments.index', array_merge(request()->except('archive'), ['archive' => 'active'])) }}"
                @class([
                    'rounded-lg px-4 py-2 text-sm font-semibold',
                    'bg-orange-600 text-white' => $archive === 'active',
                    'border border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' => $archive !== 'active',
                ])
                wire:navigate
            >Pembayaran Aktif</a>
            <a
                href="{{ route('marketing.payments.index', array_merge(request()->except('archive'), ['archive' => 'archived'])) }}"
                @class([
                    'rounded-lg px-4 py-2 text-sm font-semibold',
                    'bg-orange-600 text-white' => $archive === 'archived',
                    'border border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' => $archive !== 'archived',
                ])
                wire:navigate
            >Arsip Pembayaran</a>
        </nav>

        <section class="overflow-hidden rounded-xl border border-[#dbe2ea] bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-[#e2e8f0] px-5 py-4">
                <div>
                    <h2 class="font-semibold text-[#10233d]">{{ $archive === 'archived' ? 'Arsip pembayaran' : 'Daftar pembayaran aktif' }}</h2>
                    <p class="mt-0.5 text-sm text-[#64748b]">{{ $archive === 'archived' ? 'Riwayat booking yang pembayarannya telah diarsipkan' : 'Tagihan dan transaksi per booking' }}</p>
                </div>
                <span class="rounded-full bg-[#f1f5f9] px-3 py-1 text-sm font-semibold text-[#334155]">{{ $bookings->total() }} booking</span>
            </div>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($bookings as $row)
                    @php
                        $booking = $row['booking'];
                        $summary = $row['summary'];
                        $nextStage = $row['nextStage'];
                        $pendingOrder = $row['pendingOrder'];
                        $hasPaid = $summary['total_paid'] > 0;
                        $bookingBalanceRemaining = max(0, $summary['house_price'] - $summary['booking_paid']);
                        $isExpired = $booking->status === 'booking' && $booking->blocking_until?->isPast();
                        $paymentStatus = $pendingOrder
                            ? 'Menunggu pembayaran'
                            : ($nextStage === null && $bookingBalanceRemaining <= 0.009
                                ? 'Lunas'
                                : ($hasPaid ? 'Belum lunas' : 'Belum dibayar'));
                    @endphp
                    <article x-data class="space-y-5 rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-slate-950 dark:text-white">
                                        {{ $booking->customer->name }}
                                        <span class="font-normal text-slate-600 dark:text-slate-300">· Booking #{{ $booking->id }}</span>
                                    </h3>
                                    <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                                        {{ $booking->lot->block->property->name }} · Blok {{ $booking->lot->block->name }}/{{ $booking->lot->lot_number }}
                                    </p>
                                    <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                                        Tanggal booking {{ $booking->booking_date?->format('d/m/Y') ?? '-' }}
                                        · Status {{ $booking->status === 'finish' ? 'Serah Terima Rumah' : ucfirst($booking->status) }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if(auth()->user()->isAdmin() && $archive !== 'archived')
                                        <button type="button" x-on:click="$refs.scheduleDialog.showModal()" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-600/40 focus:ring-offset-2">
                                            Tambah Pembayaran
                                        </button>
                                    @endif
                                    <span @class([
                                        'rounded-full px-3 py-1 text-xs font-semibold',
                                        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200' => $paymentStatus === 'Menunggu pembayaran',
                                        'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200' => $paymentStatus === 'Lunas',
                                        'bg-blue-100 text-blue-900 dark:bg-blue-950 dark:text-blue-200' => $paymentStatus === 'Belum lunas',
                                        'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200' => $paymentStatus === 'Belum dibayar',
                                    ])>{{ $paymentStatus }}</span>
                                </div>
                            </div>

                            @if(auth()->user()->isAdmin() && $archive !== 'archived')
                                <dialog
                                    x-ref="scheduleDialog"
                                    x-init="$el.dataset.openOnLoad === 'true' && $el.showModal()"
                                    data-open-on-load="{{ $errors->any() && (string) old('booking_id') === (string) $booking->id ? 'true' : 'false' }}"
                                    aria-labelledby="schedule-dialog-title-{{ $booking->id }}"
                                    class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-xl border border-zinc-200 bg-white p-0 text-zinc-900 shadow-2xl backdrop:bg-slate-950/60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                                >
                                    <div class="space-y-5 p-5 sm:p-6">
                                        <header class="flex items-start justify-between gap-4">
                                            <div>
                                                <h3 id="schedule-dialog-title-{{ $booking->id }}" class="text-lg font-semibold">Tambah Pembayaran Booking</h3>
                                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $booking->lot->block->property->name }} · total jadwal tidak boleh melebihi harga kavling terendah.</p>
                                            </div>
                                            <form method="dialog">
                                                <button type="submit" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800">Tutup</button>
                                            </form>
                                        </header>

                                        @if($errors->any() && (string) old('booking_id') === (string) $booking->id)
                                            <div role="alert" class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-200">
                                                {{ $errors->first() }}
                                            </div>
                                        @endif

                                        <form method="POST" action="{{ route('admin.properties.payment-schedules.store', $booking->lot->block->property) }}" class="space-y-4">
                                            @csrf
                                            <input type="hidden" name="booking_id" value="{{ $booking->id }}">
                                            <div>
                                                <label for="schedule-title-{{ $booking->id }}" class="mb-1 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Judul pembayaran</label>
                                                <input id="schedule-title-{{ $booking->id }}" name="title" value="{{ old('booking_id') == $booking->id ? old('title') : '' }}" maxlength="100" required placeholder="Contoh: Pembayaran Tiang" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                                            </div>
                                            <div>
                                                <label for="schedule-amount-{{ $booking->id }}" class="mb-1 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Nominal (Rp)</label>
                                                <input id="schedule-amount-{{ $booking->id }}" name="amount" value="{{ old('booking_id') == $booking->id ? old('amount') : '' }}" inputmode="numeric" data-currency-input data-currency-min="1" required placeholder="1.000.000" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                                            </div>
                                            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-700">Simpan jadwal pembayaran</button>
                                        </form>
                                    </div>
                                </dialog>
                            @endif

                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach($summary['stages'] as $stage)
                                    @php
                                        $stagePendingOrder = $booking->paymentOrders->first(fn ($order): bool => $order->status === 'pending'
                                            && $order->stage === $stage['stage']
                                            && $order->booking_payment_schedule_id === $stage['schedule_id']);
                                        $stagePendingManualPayment = $booking->payments->first(fn ($payment): bool => $payment->approval_status === 'pending'
                                            && $payment->stage === $stage['stage']
                                            && $payment->booking_payment_schedule_id === $stage['schedule_id']);
                                        $canAddPayment = $stage['remaining'] > 0.009
                                            && $stage['configured']
                                            && ! $stagePendingOrder
                                            && ! $stagePendingManualPayment
                                            && ! in_array($booking->status, ['cancelled', 'finish'], true)
                                            && ! $isExpired
                                            && $archive !== 'archived';
                                    @endphp
                                    <div x-data class="rounded-lg border border-slate-300 bg-slate-50 p-3 dark:border-zinc-700 dark:bg-zinc-800">
                                        <div class="flex justify-between gap-3 text-sm">
                                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $stage['label'] }}</span>
                                            <span @class([
                                                'rounded px-2 py-0.5 font-semibold',
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $stage['remaining'] <= 0.009,
                                                'text-slate-700 dark:text-slate-200' => $stage['remaining'] > 0.009,
                                            ])>{{ $stage['remaining'] <= 0.009 ? 'Lunas' : 'Belum lunas' }}</span>
                                        </div>
                                        <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">Terbayar Rp {{ number_format($stage['paid'], 0, ',', '.') }}</p>
                                        <p class="text-sm font-semibold text-zinc-900 dark:text-white">Sisa Rp {{ number_format($stage['remaining'], 0, ',', '.') }}</p>
                                        @if($canAddPayment)
                                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                                <form method="POST" action="{{ route('marketing.bookings.payments.midtrans.store', $booking) }}">
                                                    @csrf
                                                    <input type="hidden" name="stage" value="{{ $stage['key'] }}">
                                                    @if($stage['schedule_id'])
                                                        <input type="hidden" name="booking_payment_schedule_id" value="{{ $stage['schedule_id'] }}">
                                                    @endif
                                                    @if($stage['stage'] === 'akad_fee')
                                                        <input type="hidden" name="amount" value="{{ floor($stage['remaining']) }}">
                                                    @endif
                                                    <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-orange-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-600/40 focus:ring-offset-2 dark:focus:ring-offset-zinc-800">Bayar Midtrans</button>
                                                </form>
                                                <button type="button" x-on:click="$refs.manualDialog.showModal()" class="inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-orange-600 bg-white px-3 py-2 text-sm font-semibold text-orange-700 transition hover:bg-orange-50 focus:outline-none focus:ring-2 focus:ring-orange-600/40 focus:ring-offset-2 dark:border-orange-500 dark:bg-zinc-900 dark:text-orange-300 dark:hover:bg-orange-950">Bayar Manual</button>
                                            </div>
                                        @elseif($stage['remaining'] > 0.009 && $stagePendingOrder)
                                            <p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-center text-sm font-medium text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Menunggu pembayaran tahap ini</p>
                                        @elseif($stage['remaining'] > 0.009 && $stagePendingManualPayment)
                                            <p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-center text-sm font-medium text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Menunggu persetujuan tahap ini</p>
                                        @elseif($stage['remaining'] > 0.009 && ! $stage['configured'] && ! in_array($booking->status, ['cancelled', 'finish'], true) && ! $isExpired && $archive !== 'archived')
                                            <p class="mt-3 rounded-lg border border-zinc-300 px-3 py-2 text-center text-sm font-medium text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">Jadwal belum diatur admin</p>
                                        @endif
                                        @if($canAddPayment)
                                            <dialog
                                                id="payment-dialog-{{ $booking->id }}-{{ $stage['key'] }}"
                                                x-ref="manualDialog"
                                                x-init="$el.dataset.openOnLoad === 'true' && $el.showModal()"
                                                data-open-on-load="{{ $errors->any() && old('stage') === $stage['key'] ? 'true' : 'false' }}"
                                                aria-labelledby="payment-dialog-title-{{ $booking->id }}-{{ $stage['key'] }}"
                                                class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-xl border border-zinc-200 bg-white p-0 text-zinc-900 shadow-2xl backdrop:bg-slate-950/60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                                            >
                                                <div class="space-y-5 p-5 sm:p-6">
                                                    <header class="flex items-start justify-between gap-4">
                                                        <div>
                                                            <h3 id="payment-dialog-title-{{ $booking->id }}-{{ $stage['key'] }}" class="text-lg font-semibold">Pembayaran Manual · {{ $stage['label'] }}</h3>
                                                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Nominal ditetapkan admin. Sisa tagihan tahap ini Rp {{ number_format($stage['remaining'], 0, ',', '.') }}.</p>
                                                        </div>
                                                        <form method="dialog">
                                                            <button type="submit" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800">Tutup</button>
                                                        </form>
                                                    </header>

                                                    @if($errors->any() && old('stage') === $stage['key'])
                                                        <div role="alert" class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-200">
                                                            {{ $errors->first() }}
                                                        </div>
                                                    @endif

                                                    <form method="POST" enctype="multipart/form-data" action="{{ route('marketing.bookings.payments.store', $booking) }}" class="space-y-3 rounded-lg border border-slate-300 bg-slate-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                                                        @csrf
                                                        <input type="hidden" name="stage" value="{{ $stage['key'] }}">
                                                        @if($stage['schedule_id'])
                                                            <input type="hidden" name="booking_payment_schedule_id" value="{{ $stage['schedule_id'] }}">
                                                        @endif
                                                        <input type="hidden" name="paid_at" value="{{ now()->format('Y-m-d H:i') }}">
                                                        <div>
                                                            <p class="text-sm text-zinc-600 dark:text-zinc-300">Nominal pembayaran: Rp {{ number_format($stage['remaining'], 0, ',', '.') }}</p>
                                                            @if($stage['stage'] === 'akad_fee')
                                                                <label for="payment-amount-{{ $booking->id }}-{{ $stage['key'] }}" class="mt-3 mb-1.5 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Jumlah pembayaran</label>
                                                                <input id="payment-amount-{{ $booking->id }}-{{ $stage['key'] }}" type="text" name="amount" inputmode="numeric" data-currency-input data-currency-min="1" data-currency-max="{{ floor($stage['remaining']) }}" required value="{{ old('stage') === $stage['key'] ? old('amount', floor($stage['remaining'])) : floor($stage['remaining']) }}" class="w-full rounded-lg border border-slate-400 bg-white px-3 py-2 text-slate-950 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white">
                                                                @error('amount')<p class="text-sm font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <label for="payment-method-{{ $booking->id }}-{{ $stage['key'] }}" class="mb-1.5 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Metode pembayaran</label>
                                                            <input id="payment-method-{{ $booking->id }}-{{ $stage['key'] }}" type="text" name="payment_method" value="{{ old('stage') === $stage['key'] ? old('payment_method') : '' }}" placeholder="Transfer bank / cash" class="w-full rounded-lg border border-slate-400 bg-white px-3 py-2 text-slate-950 placeholder:text-slate-500 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white">
                                                        </div>
                                                        <div>
                                                            <label for="payment-notes-{{ $booking->id }}-{{ $stage['key'] }}" class="mb-1.5 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Keterangan</label>
                                                            <textarea id="payment-notes-{{ $booking->id }}-{{ $stage['key'] }}" name="notes" rows="2" placeholder="Keterangan tambahan" class="w-full rounded-lg border border-slate-400 bg-white px-3 py-2 text-slate-950 placeholder:text-slate-500 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white">{{ old('stage') === $stage['key'] ? old('notes') : '' }}</textarea>
                                                        </div>
                                                        <div>
                                                            <label for="payment-proof-{{ $booking->id }}-{{ $stage['key'] }}" class="mb-1.5 block text-sm font-medium text-zinc-800 dark:text-zinc-200">Bukti pembayaran</label>
                                                            <input id="payment-proof-{{ $booking->id }}-{{ $stage['key'] }}" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" required class="w-full text-sm">
                                                        </div>
                                                        <button type="submit" class="w-full rounded-lg border border-orange-600 px-4 py-2.5 text-sm font-semibold text-orange-700 hover:bg-orange-50 dark:text-orange-300 dark:hover:bg-orange-950">Ajukan Pembayaran Manual</button>
                                                        <p class="text-xs text-slate-600 dark:text-slate-300">Pembayaran manual menunggu persetujuan admin.</p>
                                                    </form>
                                                </div>
                                            </dialog>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            @if($pendingOrder)
                                <section class="space-y-2 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
                                    <h4 class="font-semibold text-amber-900 dark:text-amber-200">Transaksi Midtrans belum terkonfirmasi</h4>
                                    <p class="text-sm text-amber-900 dark:text-amber-100">
                                        {{ $pendingOrder->paymentSchedule?->title ?? match ($pendingOrder->stage) {
                                            'dp', 'booking' => 'Pembayaran Booking',
                                            'booking_balance' => 'Sisa Pembayaran Booking',
                                            'akad_fee' => 'Biaya Akad',
                                            default => \Illuminate\Support\Str::headline($pendingOrder->stage),
                                        } }} · Rp {{ number_format((float) $pendingOrder->amount, 0, ',', '.') }}
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        @if($pendingOrder->redirect_url)
                                            <a href="{{ $pendingOrder->redirect_url }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700">Lanjutkan pembayaran Midtrans</a>
                                        @else
                                            <p class="text-sm text-amber-900 dark:text-amber-100">Transaksi sedang diperiksa. Jangan membuat pembayaran baru untuk tahap ini.</p>
                                        @endif
                                        <form method="POST" action="{{ route('marketing.bookings.payments.midtrans.refresh', $booking) }}">
                                            @csrf
                                            <button type="submit" class="min-h-10 rounded-lg border border-amber-700 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100 dark:text-amber-200 dark:hover:bg-amber-950">Cek status pembayaran</button>
                                        </form>
                                    </div>
                                </section>
                            @endif

                            @if($isExpired && $hasPaid && $nextStage !== null)
                                <section class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm dark:border-amber-800 dark:bg-zinc-900">
                                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-100 bg-amber-50 px-4 py-3 dark:border-amber-900 dark:bg-amber-950/30">
                                        <div>
                                            <h4 class="font-semibold text-zinc-900 dark:text-zinc-100">Alasan pembayaran belum lunas</h4>
                                            <p class="mt-0.5 text-sm text-zinc-800 dark:text-zinc-200">Booking melewati batas waktu dan masih memiliki tagihan.</p>
                                        </div>
                                        @if($booking->unpaid_payment_reason_status === 'pending')
                                            <span class="inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-zinc-900 dark:bg-blue-900/60 dark:text-white">
                                                <span class="size-2 rounded-full bg-blue-600 dark:bg-blue-300" aria-hidden="true"></span>
                                                Menunggu persetujuan admin
                                            </span>
                                        @elseif($booking->unpaid_payment_reason_status === 'rejected')
                                            <span class="inline-flex items-center gap-2 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-zinc-900 dark:bg-rose-900/50 dark:text-white">
                                                <span class="size-2 rounded-full bg-rose-600 dark:bg-rose-300" aria-hidden="true"></span>
                                                Ditolak admin
                                            </span>
                                        @elseif($booking->unpaid_payment_reason_status === 'approved')
                                            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-zinc-900 dark:bg-emerald-900/50 dark:text-white">
                                                <span class="size-2 rounded-full bg-emerald-600 dark:bg-emerald-300" aria-hidden="true"></span>
                                                Disetujui admin
                                            </span>
                                        @endif
                                    </div>

                                    <div class="space-y-3 p-4">
                                        @if($booking->unpaid_payment_reason_status === 'approved')
                                            <div role="status" class="flex flex-col gap-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-emerald-800 dark:bg-emerald-950/30">
                                                <div>
                                                    <p class="font-semibold text-zinc-900 dark:text-zinc-100">Alasan telah disetujui admin.</p>
                                                    <p class="mt-1 text-sm leading-6 text-zinc-800 dark:text-zinc-200">Silakan perbarui tanggal booking dan batas booking agar masa booking kembali sesuai.</p>
                                                </div>
                                                <a href="{{ route('marketing.bookings.edit', $booking) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600/40 focus:ring-offset-2 dark:bg-emerald-600 dark:hover:bg-emerald-500" wire:navigate>
                                                    Edit Booking
                                                </a>
                                            </div>
                                            <div>
                                                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Alasan yang disetujui</p>
                                                <p class="whitespace-pre-line rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm leading-6 text-zinc-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">{{ $booking->unpaid_payment_reason }}</p>
                                            </div>
                                        @elseif($booking->unpaid_payment_reason_status === 'pending' || $booking->unpaid_payment_reason_status === 'rejected')
                                            <div>
                                                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Keterangan</p>
                                                <p class="whitespace-pre-line rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm leading-6 text-zinc-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">{{ $booking->unpaid_payment_reason }}</p>
                                            </div>
                                            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                                                {{ $booking->unpaid_payment_reason_status === 'pending'
                                                    ? 'Alasan sedang diperiksa admin dan tidak dapat diubah selama proses pemeriksaan.'
                                                    : 'Alasan ini sudah ditolak dan tidak dapat diubah.' }}
                                            </p>
                                        @else
                                            <form method="POST" action="{{ route('marketing.payments.unpaid-reason.update', $booking) }}" class="space-y-3">
                                                @csrf
                                                @method('PUT')
                                                <label for="unpaid-payment-reason-{{ $booking->id }}" class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                                    Jelaskan kendala pembayaran
                                                </label>
                                                <textarea id="unpaid-payment-reason-{{ $booking->id }}" name="unpaid_payment_reason" rows="3" required minlength="10" maxlength="2000" placeholder="Tuliskan kendala atau alasan pembayaran belum selesai..." class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white dark:placeholder:text-zinc-500">{{ old('unpaid_payment_reason', $booking->unpaid_payment_reason) }}</textarea>
                                                @error('unpaid_payment_reason')<p role="alert" class="text-sm font-medium text-rose-700 dark:text-rose-300">{{ $message }}</p>@enderror
                                                <div class="flex justify-end">
                                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-600/40 focus:ring-offset-2 dark:bg-amber-600 dark:hover:bg-amber-500">
                                                        Ajukan alasan
                                                    </button>
                                                </div>
                                            </form>
                                        @endif
                                    </div>
                                </section>
                            @endif

                            @if($archive === 'archived')
                                <form method="POST" action="{{ route('marketing.payments.archive.destroy', $booking) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-amber-700 bg-white px-4 py-2.5 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-600/30 focus:ring-offset-2 dark:border-amber-500 dark:bg-zinc-900 dark:text-amber-200 dark:hover:bg-amber-950">Kembalikan ke pembayaran aktif</button>
                                </form>
                            @elseif($row['canArchive'])
                                <form method="POST" action="{{ route('marketing.payments.archive.store', $booking) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600/40 focus:ring-offset-2 dark:bg-emerald-600 dark:hover:bg-emerald-500">Arsipkan pembayaran lunas</button>
                                </form>
                            @endif
                            <a href="{{ route('marketing.bookings.show', $booking) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border-2 border-orange-700 bg-white px-4 py-2.5 text-center text-sm font-bold text-orange-950 shadow-sm transition hover:bg-orange-100 focus:outline-none focus:ring-2 focus:ring-orange-600/30 focus:ring-offset-2 dark:border-orange-400 dark:bg-zinc-900 dark:text-orange-100 dark:hover:bg-orange-950" wire:navigate>Lihat detail booking dan riwayat pembayaran</a>
                        </div>

                    </article>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">Tidak ada booking yang cocok dengan filter.</p>
                @endforelse
            </div>
            @if($bookings->hasPages())
                <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">{{ $bookings->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts::app>
