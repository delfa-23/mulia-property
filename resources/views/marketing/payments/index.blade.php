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
                        $isExpired = $booking->status === 'booking' && $booking->blocking_until?->isPast();
                        $paymentStatus = $pendingOrder
                            ? 'Menunggu pembayaran'
                            : ($nextStage === null ? 'Lunas' : ($hasPaid ? 'Belum lunas' : 'Belum dibayar'));
                    @endphp
                    <article class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.8fr)]">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-zinc-900 dark:text-white">
                                        {{ $booking->customer->name }}
                                        <span class="font-normal text-zinc-500 dark:text-zinc-400">· Booking #{{ $booking->id }}</span>
                                    </h3>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $booking->lot->block->property->name }} · Blok {{ $booking->lot->block->name }}/{{ $booking->lot->lot_number }}
                                    </p>
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        Tanggal booking {{ $booking->booking_date?->format('d/m/Y') ?? '-' }}
                                        · Status {{ $booking->status === 'finish' ? 'Serah Terima Rumah' : ucfirst($booking->status) }}
                                    </p>
                                </div>
                                <span @class([
                                    'rounded-full px-3 py-1 text-xs font-semibold',
                                    'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200' => $paymentStatus === 'Menunggu pembayaran',
                                    'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200' => $paymentStatus === 'Lunas',
                                    'bg-blue-100 text-blue-900 dark:bg-blue-950 dark:text-blue-200' => $paymentStatus === 'Belum lunas',
                                    'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200' => $paymentStatus === 'Belum dibayar',
                                ])>{{ $paymentStatus }}</span>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach($summary['stages'] as $stage)
                                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                        <div class="flex justify-between gap-3 text-sm">
                                            <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $stage['label'] }}</span>
                                            <span class="text-zinc-500 dark:text-zinc-400">{{ $stage['remaining'] <= 0.009 ? 'Lunas' : 'Belum lunas' }}</span>
                                        </div>
                                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Terbayar Rp {{ number_format($stage['paid'], 0, ',', '.') }}</p>
                                        <p class="text-sm font-semibold text-zinc-900 dark:text-white">Sisa Rp {{ number_format($stage['remaining'], 0, ',', '.') }}</p>
                                    </div>
                                @endforeach
                            </div>

                            @if($booking->payments->isNotEmpty() || $booking->paymentOrders->isNotEmpty())
                                <div class="grid gap-3 lg:grid-cols-2">
                                    @if($booking->payments->isNotEmpty())
                                        <section class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Riwayat pembayaran</h4>
                                            <ul class="mt-2 divide-y divide-zinc-200 dark:divide-zinc-700">
                                                @foreach($booking->payments as $payment)
                                                    <li class="space-y-1 py-2 text-xs text-zinc-600 dark:text-zinc-300">
                                                        <p class="font-medium text-zinc-800 dark:text-zinc-100">
                                                            {{ $payment->paid_at?->format('d/m/Y H:i') ?? '-' }}
                                                            · {{ ['dp' => 'Pembayaran DP', 'akad_fee' => 'Biaya Akad', 'bank_settlement' => 'Pembayaran DP (Bank)'][$payment->stage] ?? ucfirst($payment->stage) }}
                                                            · Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                                        </p>
                                                        <p>
                                                            {{ $payment->payment_method ?: 'Metode tidak tersedia' }}
                                                            @if($payment->reference) · Referensi {{ $payment->reference }} @endif
                                                            @if($payment->recordedBy) · PIC {{ $payment->recordedBy->name }} @endif
                                                        </p>
                                                        @if($payment->paymentOrder)
                                                            <p>
                                                                Order {{ $payment->paymentOrder->order_id }}
                                                                · ID transaksi {{ $payment->paymentOrder->transaction_id ?: '-' }}
                                                            </p>
                                                        @endif
                                                        @if($payment->notes)
                                                            <p>{{ $payment->notes }}</p>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </section>
                                    @endif

                                    @if($booking->paymentOrders->isNotEmpty())
                                        <section class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                            <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Riwayat transaksi Midtrans</h4>
                                            <ul class="mt-2 divide-y divide-zinc-200 dark:divide-zinc-700">
                                                @foreach($booking->paymentOrders as $paymentOrder)
                                                    <li class="space-y-1 py-2 text-xs text-zinc-600 dark:text-zinc-300">
                                                        <p class="font-medium text-zinc-800 dark:text-zinc-100">
                                                            {{ $paymentOrder->created_at?->format('d/m/Y H:i') ?? '-' }}
                                                            · {{ $paymentOrder->stage === 'dp' ? 'Pembayaran DP' : 'Biaya Akad' }}
                                                            · {{ [
                                                                'pending' => 'Menunggu konfirmasi',
                                                                'paid' => 'Berhasil',
                                                                'failed' => 'Gagal',
                                                                'expired' => 'Kedaluwarsa',
                                                                'cancelled' => 'Dibatalkan',
                                                            ][$paymentOrder->status] ?? ucfirst($paymentOrder->status) }}
                                                            · Rp {{ number_format((float) $paymentOrder->amount, 0, ',', '.') }}
                                                        </p>
                                                        <p>
                                                            Order {{ $paymentOrder->order_id }}
                                                            · ID transaksi {{ $paymentOrder->transaction_id ?: '-' }}
                                                            · Metode {{ $paymentOrder->payment_type ? str_replace('_', ' ', $paymentOrder->payment_type) : '-' }}
                                                        </p>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </section>
                                    @endif
                                </div>
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
                            <a href="{{ route('marketing.bookings.show', $booking) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[#c2410c] bg-white px-4 py-2.5 text-center text-sm font-semibold text-[#9a3412] shadow-sm transition hover:bg-orange-50 focus:outline-none focus:ring-2 focus:ring-orange-600/30 focus:ring-offset-2 dark:border-orange-500 dark:bg-zinc-900 dark:text-orange-300 dark:hover:bg-orange-950" wire:navigate>Lihat detail booking dan riwayat pembayaran</a>
                        </div>

                        <div class="flex flex-col justify-center gap-3 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/60">
                            @if($pendingOrder)
                                <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Transaksi Midtrans belum terkonfirmasi</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ $pendingOrder->stage === 'dp' ? 'Pembayaran DP' : 'Biaya Akad' }} · Rp {{ number_format((float) $pendingOrder->amount, 0, ',', '.') }}
                                </p>
                                @if($pendingOrder->redirect_url)
                                    <a href="{{ $pendingOrder->redirect_url }}" class="inline-flex justify-center rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-700">Lanjutkan pembayaran Midtrans</a>
                                @else
                                    <p class="text-sm text-zinc-600 dark:text-zinc-300">Transaksi sedang diperiksa. Jangan membuat pembayaran baru untuk tahap ini.</p>
                                @endif
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">Jika customer sudah membayar, cek status untuk menyinkronkan konfirmasi terbaru dari Midtrans.</p>
                                <form method="POST" action="{{ route('marketing.bookings.payments.midtrans.refresh', $booking) }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-lg border border-amber-700 px-4 py-2.5 text-sm font-semibold text-amber-900 hover:bg-amber-100 dark:text-amber-200 dark:hover:bg-amber-950">Cek status pembayaran</button>
                                </form>
                            @elseif($nextStage && ! in_array($booking->status, ['cancelled', 'finish'], true) && ! $isExpired && floor($nextStage['remaining']) >= 1)
                                <form method="POST" action="{{ route('marketing.bookings.payments.midtrans.store', $booking) }}" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="stage" value="{{ $nextStage['key'] }}">
                                    <div>
                                        <label for="payment-amount-{{ $booking->id }}" class="mb-1.5 block text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                            {{ $nextStage['label'] }} · Sisa Rp {{ number_format($nextStage['remaining'], 0, ',', '.') }}
                                        </label>
                                        <input id="payment-amount-{{ $booking->id }}" type="number" name="amount" min="1" max="{{ floor($nextStage['remaining']) }}" step="1" required value="{{ floor($nextStage['remaining']) }}" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-600 dark:bg-zinc-900">
                                    </div>
                                    <p class="text-xs leading-5 text-zinc-600 dark:text-zinc-400">Customer memilih Virtual Account, QRIS, e-wallet, atau metode lain yang tersedia di halaman Midtrans.</p>
                                    <button type="submit" class="w-full rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-700">Bayar via Midtrans</button>
                                    @error('stage')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                                    @error('amount')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                                </form>
                            @elseif($booking->status === 'cancelled' || $booking->status === 'finish')
                                <p class="text-sm text-zinc-600 dark:text-zinc-300">Pembayaran online tidak tersedia untuk booking {{ $booking->status === 'finish' ? 'yang sudah diserahterimakan' : 'yang dibatalkan' }}.</p>
                            @elseif($isExpired)
                                <p class="text-sm text-amber-800 dark:text-amber-200">Batas booking terlewati. Perbarui status booking sebelum membuat pembayaran.</p>
                            @elseif($nextStage)
                                <p class="text-sm text-zinc-600 dark:text-zinc-300">Sisa tagihan tahap ini kurang dari nominal minimum pembayaran online.</p>
                            @else
                                <p class="font-semibold text-emerald-700 dark:text-emerald-300">Semua tahap pembayaran sudah lunas.</p>
                            @endif
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
