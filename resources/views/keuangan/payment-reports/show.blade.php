<x-layouts::app :title="'Detail Pembayaran Kavling'">
    <div class="space-y-6">
        <div>
            <a href="{{ route('keuangan.payment-reports.index') }}" class="text-sm font-semibold text-orange-700">← Kembali ke laporan pembayaran</a>
            <h1 class="mt-3 text-2xl font-semibold text-[#10233D] dark:text-white">{{ $booking->lot->block->property->name ?? '-' }} · Kavling {{ $booking->lot->lot_number }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ $booking->customer->name ?? '-' }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            @foreach($summary['stages'] as $stage)
                <section class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-500">{{ $stage['label'] }}</p>
                    <p class="mt-2 text-xl font-bold">Rp {{ number_format($stage['paid'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-sm text-orange-700">Sisa Rp {{ number_format($stage['remaining'], 0, ',', '.') }}</p>
                    <p class="text-xs text-zinc-500">Dari Rp {{ number_format($stage['due'], 0, ',', '.') }}</p>
                </section>
            @endforeach
            <section class="rounded-xl bg-[#10233D] p-4 text-white sm:col-span-3">
                <p class="text-sm text-blue-100">Total sisa seluruh tahap</p>
                <p class="mt-1 text-2xl font-bold">Rp {{ number_format(collect($summary['stages'])->sum('remaining'), 0, ',', '.') }}</p>
            </section>
        </div>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b px-4 py-4 dark:border-zinc-700"><h2 class="font-semibold">Riwayat Pembayaran</h2></div>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($booking->payments->sortByDesc('paid_at') as $payment)
                    <div class="grid gap-2 px-4 py-4 sm:grid-cols-[150px_1fr_auto] sm:items-center">
                        <div>
                            <p class="text-sm font-medium">{{ $payment->paid_at->format('d/m/Y H:i') }}</p>
                            <p class="text-xs text-zinc-500">{{ ucfirst($payment->payment_channel ?? 'manual') }}</p>
                        </div>
                        <div>
                            <p class="font-medium">{{ $payment->stage === 'akad_fee' ? 'Biaya Akad' : 'Pembayaran Rumah' }}</p>
                            <p class="text-sm text-zinc-500">{{ $payment->notes ?: '-' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</p>
                            <p @class(['text-xs font-semibold', 'text-emerald-700' => $payment->approval_status === 'approved', 'text-amber-700' => $payment->approval_status === 'pending', 'text-red-700' => $payment->approval_status === 'rejected'])>
                                {{ ucfirst($payment->approval_status) }}
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-10 text-center text-sm text-zinc-500">Belum ada pembayaran.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts::app>
