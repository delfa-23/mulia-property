<x-layouts::app :title="__('Detail Booking')">
    @php
        $nextPaymentStage = collect($paymentSummary['stages'])
            ->first(fn (array $stage): bool => $stage['remaining'] > 0.009);
        $pendingPaymentOrder = $booking->paymentOrders
            ->first(fn ($order): bool => $order->status === 'pending');
        $autoCancelledByDeadline = $booking->histories->contains(
            fn ($history): bool => $history->to_status === 'cancelled'
                && $history->notes === 'Booking dibatalkan otomatis karena batas blocking terlewati.'
        );
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <a href="{{ route('marketing.bookings.index') }}" class="text-sm text-zinc-500" wire:navigate>Kembali ke Booking</a>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">Detail Booking</h1>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('marketing.bookings.edit', $booking) }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" wire:navigate>Edit Booking</a>
                    <a href="{{ route('marketing.bookings.documents.edit', $booking) }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800" wire:navigate>Edit Berkas</a>
                    @if(auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('marketing.bookings.destroy', $booking) }}" onsubmit="return confirm('Hapus permanen booking ini beserta seluruh data terkait?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Hapus Booking</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        @if(session('success'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif
        @if(session('document_delete_warning'))
            <div role="alert" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('document_delete_warning') }}</div>
        @endif
        @if(session('error'))
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif
        @if(session('info'))
            <div role="status" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">{{ session('info') }}</div>
        @endif

        @if(($booking->status === 'booking' && $booking->blocking_until?->isPast() && ! $pendingPaymentOrder) || $autoCancelledByDeadline)
            <p role="alert" class="rounded-md bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">
                @if($autoCancelledByDeadline)
                    Booking otomatis dibatalkan karena batas booking terlewati pada {{ $booking->blocking_until?->format('d/m/Y H:i') }}.
                @else
                    Deadline blocking {{ $booking->customer->name }} terlewati pada {{ $booking->blocking_until->format('d/m/Y H:i') }}. Booking akan dihapus otomatis jika belum ada pembayaran.
                @endif
            </p>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-6">
                <h2 class="font-semibold">Customer & Kavling</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-zinc-500">Nama Customer</dt><dd class="font-medium">{{ $booking->customer->name }}</dd></div>
                    <div><dt class="text-zinc-500">NIK</dt><dd class="font-medium">{{ $booking->customer->nik ?: '-' }}</dd></div>
                    <div><dt class="text-zinc-500">Telepon</dt><dd class="font-medium">{{ $booking->customer->phone ?: '-' }}</dd></div>
                    <div><dt class="text-zinc-500">Perumahan / Kavling</dt><dd class="font-medium">{{ $booking->lot->block->property->name }} / {{ $booking->lot->block->name }}-{{ $booking->lot->lot_number }}</dd></div>
                    <div><dt class="text-zinc-500">Sales</dt><dd class="font-medium">{{ $booking->sales?->name ?? '-' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-6">
                <h2 class="font-semibold">Status Booking</h2>
                <p class="mt-2 text-xl font-semibold">{{ ucfirst($booking->status) }}</p>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-zinc-500">Tanggal Booking</dt><dd class="font-medium">{{ $booking->booking_date?->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-zinc-500">Batas Booking</dt><dd class="font-medium">{{ $booking->blocking_until?->format('d/m/Y H:i') ?? 'Tidak ditentukan' }}</dd></div>
                    <div><dt class="text-zinc-500">Catatan</dt><dd class="font-medium">{{ $booking->notes ?: '-' }}</dd></div>
                </dl>
            </section>
        </div>

        <section class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Berkas Booking</h2>
                    <p class="mt-1 text-sm text-zinc-500">Berkas di sini tersimpan bersama dan dapat dilihat oleh Marketing maupun Pemberkasan.</p>
                    <p class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        BI Checking:
                        {{ ucfirst(str_replace('_', ' ', $booking->documentProcess?->bi_checking_status ?? 'not_checked')) }}
                    </p>
                </div>
            </div>

            <p class="rounded-lg bg-zinc-50 p-4 text-sm text-zinc-600 dark:bg-zinc-800/60 dark:text-zinc-300">
                Dokumen dapat dilihat di halaman detail ini. Untuk mengunggah atau mengganti dokumen, gunakan Edit Berkas.
            </p>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach([
                    'Lokasi kerja pemohon' => $booking->applicant_work_location_url,
                    'Lokasi kerja pasangan' => $booking->spouse_work_location_url,
                ] as $locationLabel => $locationUrl)
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <p class="text-xs text-zinc-500">{{ $locationLabel }}</p>
                        @if($locationUrl)
                            <a href="{{ $locationUrl }}" target="_blank" rel="noopener noreferrer" class="mt-1 block break-all text-sm font-medium text-blue-700 hover:underline dark:text-blue-300">Buka Google Maps</a>
                        @else
                            <p class="mt-1 text-sm">Belum diisi</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($booking->pemberkasanDocuments as $document)
                    <article class="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-medium">{{ $document->document_type }} <span class="font-normal text-zinc-500">· {{ $document->original_name }}</span></p>
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ $document->document_category === 'other' ? 'Berkas lainnya' : (in_array($document->document_type, \App\Services\PemberkasanDocumentService::BI_CHECKING_DOCUMENT_TYPES, true) ? 'Berkas BI Checking' : 'Berkas utama') }}
                                · Diunggah oleh {{ $document->uploadedBy?->name ?? 'Pengguna tidak tersedia' }}
                                @if($document->uploaded_at) · {{ $document->uploaded_at->format('d/m/Y H:i') }} @endif
                            </p>
                            @if($document->status === 'uploaded')
                                <p class="mt-2 text-sm font-medium text-emerald-700 dark:text-emerald-300">Tersimpan di Google Drive</p>
                            @elseif($document->status === 'archived')
                                <p class="mt-2 text-sm font-medium text-zinc-500">Diarsipkan. File telah dihapus dari Google Drive karena kavling dialihkan.</p>
                            @elseif($document->status === 'failed')
                                <p class="mt-2 text-sm font-medium text-rose-700 dark:text-rose-300">Upload Google Drive gagal</p>
                                @if($document->upload_error)<p class="mt-1 text-sm text-rose-600">{{ $document->upload_error }}</p>@endif
                            @else
                                <p class="mt-2 text-sm font-medium text-amber-700 dark:text-amber-300">{{ $document->status === 'uploading' ? 'Sedang diunggah' : 'Menunggu upload Google Drive' }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            @if($document->drive_url && $document->status === 'uploaded')
                                <a href="{{ $document->drive_url }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-blue-700 hover:underline dark:text-blue-300">Buka berkas</a>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="py-4 text-sm text-zinc-500">Belum ada berkas yang terkait dengan booking ini.</p>
                @endforelse
            </div>
        </section>

        <section class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Rincian Pembayaran</h2>
                    <p class="mt-1 text-sm text-zinc-500">Pembayaran booking mengikuti jadwal yang ditentukan admin sampai lunas 100% harga rumah.</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-zinc-500">Harga Kavling</p>
                    <p class="text-lg font-semibold">Rp {{ number_format($paymentSummary['house_price'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div>
                <div class="flex justify-between gap-4 text-sm">
                    <span>Pembayaran booking terbayar dari harga kavling ({{ number_format($paymentSummary['booking_paid_percentage'], 2) }}%)</span>
                    <span>Rp {{ number_format($paymentSummary['total_paid_price'], 0, ',', '.') }}</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200">
                    <div class="h-full rounded-full bg-green-600" style="width: {{ $paymentSummary['paid_percentage'] }}%"></div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                @foreach($paymentSummary['stages'] as $stage)
                    <article class="rounded-lg border border-zinc-200 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-medium">{{ $stage['label'] }}</h3>
                            <flux:badge :color="$stage['remaining'] <= 0.009 ? 'green' : 'zinc'">
                                {{ $stage['remaining'] <= 0.009 ? 'Lunas' : 'Belum lunas' }}
                            </flux:badge>
                        </div>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-2"><dt class="text-zinc-500">Tagihan</dt><dd>Rp {{ number_format($stage['due'], 0, ',', '.') }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-zinc-500">Terbayar</dt><dd>Rp {{ number_format($stage['paid'], 0, ',', '.') }}</dd></div>
                            <div class="flex justify-between gap-2 font-medium"><dt>Sisa</dt><dd>Rp {{ number_format($stage['remaining'], 0, ',', '.') }}</dd></div>
                        </dl>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-zinc-200">
                            <div class="h-full rounded-full bg-blue-600" style="width: {{ $stage['paid_percentage'] }}%"></div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="flex justify-end">
                <a href="{{ route('marketing.payments.index', ['search' => $booking->customer?->name]) }}" class="inline-flex rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50" wire:navigate>
                    Kelola di Menu Pembayaran
                </a>
            </div>
        </section>

        @if($booking->payments->isNotEmpty() || $booking->paymentOrders->isNotEmpty())
            <section class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <div>
                    <h2 class="text-lg font-semibold">Riwayat Transaksi Pembayaran</h2>
                    <p class="mt-1 text-sm text-zinc-500">Rincian pembayaran manual dan transaksi Midtrans untuk booking ini.</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    @if($booking->payments->isNotEmpty())
                        <section class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <h3 class="font-semibold">Riwayat pembayaran</h3>
                            <ul class="mt-2 divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($booking->payments as $payment)
                                    <li class="space-y-1 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                        <p class="font-medium text-zinc-800 dark:text-zinc-100">
                                            {{ $payment->paid_at?->format('d/m/Y H:i') ?? '-' }}
                                            · {{ $payment->paymentSchedule?->title ?? match ($payment->stage) {
                                                'dp', 'bank_settlement', 'booking' => 'Pembayaran Booking',
                                                'booking_balance' => 'Sisa Pembayaran Booking',
                                                'akad_fee' => 'Biaya Akad',
                                                default => \Illuminate\Support\Str::headline($payment->stage),
                                            } }}
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
                        <section class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <h3 class="font-semibold">Riwayat transaksi Midtrans</h3>
                            <ul class="mt-2 divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($booking->paymentOrders as $paymentOrder)
                                    <li class="space-y-1 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                        <p class="font-medium text-zinc-800 dark:text-zinc-100">
                                            {{ $paymentOrder->created_at?->format('d/m/Y H:i') ?? '-' }}
                                            · {{ $paymentOrder->paymentSchedule?->title ?? match ($paymentOrder->stage) {
                                                'dp', 'booking' => 'Pembayaran Booking',
                                                'booking_balance' => 'Sisa Pembayaran Booking',
                                                'akad_fee' => 'Biaya Akad',
                                                default => \Illuminate\Support\Str::headline($paymentOrder->stage),
                                            } }}
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
            </section>
        @endif

        <section class="rounded-xl border border-zinc-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Riwayat Booking</h2>
            <div class="mt-4 divide-y divide-zinc-200">
                @forelse($booking->histories as $history)
                    <div class="flex justify-between gap-4 py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ $history->from_status ? ucfirst($history->from_status).' → ' : '' }}{{ ucfirst($history->to_status) }}</p>
                            <p class="text-zinc-500">{{ $history->notes ?: '-' }} · {{ $history->changedBy?->name ?? 'Sistem' }}</p>
                        </div>
                        <span class="whitespace-nowrap text-zinc-500">{{ $history->changed_at?->format('d/m/Y H:i') }}</span>
                    </div>
                @empty
                    <p class="py-4 text-sm text-zinc-500">Belum ada riwayat.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts::app>
