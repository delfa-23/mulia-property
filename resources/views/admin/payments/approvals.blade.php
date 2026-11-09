<x-layouts::app :title="'Persetujuan Pembayaran Manual'">
    <div class="space-y-6">
        <div class="rounded-2xl border-l-4 border-amber-500 bg-[#10233D] px-6 py-5 shadow-md">
            <h1 class="text-2xl font-bold text-white">Persetujuan Pembayaran Manual</h1>
            <p class="mt-1 text-sm text-slate-200">Periksa bukti pembayaran sebelum mengubah status menjadi masuk.</p>
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 font-medium text-emerald-800">{{ session('success') }}</div>
        @endif

        @forelse($payments as $payment)
            <article class="overflow-hidden rounded-2xl border border-slate-300 bg-white shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-l-4 border-amber-500 bg-[#FFF2CC] px-5 py-4 dark:bg-amber-950/30">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-[#10233D] dark:text-white">{{ $payment->booking->customer->name ?? '-' }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $payment->booking->lot->block->property->name ?? '-' }} · Kavling {{ $payment->booking->lot->lot_number }}
                            </p>
                        </div>
                        <span class="rounded-full bg-amber-200 px-3 py-1 text-xs font-bold text-amber-900">MENUNGGU REVIEW</span>
                    </div>
                </div>
                <div class="grid gap-5 p-5 lg:grid-cols-[1fr_auto]">
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="text-zinc-500">Tahap</dt><dd class="font-semibold">{{ $payment->stage === 'akad_fee' ? 'Biaya Akad' : 'Pembayaran Rumah' }}</dd></div>
                        <div><dt class="text-zinc-500">Nominal</dt><dd class="text-lg font-bold text-[#10233D] dark:text-white">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</dd></div>
                        <div><dt class="text-zinc-500">Tanggal Bayar</dt><dd class="font-medium">{{ $payment->paid_at->format('d/m/Y H:i') }}</dd></div>
                        <div><dt class="text-zinc-500">Metode</dt><dd class="font-medium">{{ $payment->payment_method ?: '-' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-zinc-500">Keterangan</dt><dd class="mt-1 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800">{{ $payment->notes ?: '-' }}</dd></div>
                    </dl>
                    <div class="flex min-w-72 flex-col justify-end gap-3">
                        @if($payment->proof_path)
                            <a href="{{ Storage::url($payment->proof_path) }}" target="_blank" class="rounded-lg border border-blue-300 bg-blue-50 px-4 py-2.5 text-center text-sm font-bold text-blue-800 hover:bg-blue-100">Lihat Bukti Pembayaran</a>
                        @endif
                        <form method="POST" action="{{ route('admin.payments.approve', $payment) }}">
                            @csrf
                            <button class="w-full rounded-lg bg-emerald-700 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">Setujui Pembayaran</button>
                        </form>
                        <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="space-y-2 rounded-xl border border-red-400 bg-red-100 p-3 text-slate-950 shadow-sm dark:border-red-800 dark:bg-red-950/40 dark:text-white">
                            @csrf
                            <label class="text-sm font-bold text-red-950 dark:text-red-200">Alasan penolakan</label>
                            <textarea name="rejection_reason" required maxlength="500" rows="2" class="w-full rounded-lg border-red-400 bg-white px-3 py-2 text-sm text-slate-950 focus:border-red-600 focus:ring-red-600 dark:border-red-800 dark:bg-zinc-950 dark:text-white"></textarea>
                            <button class="w-full rounded-lg border border-red-800 bg-red-700 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-red-800 dark:bg-red-600 dark:hover:bg-red-500">Tolak Pembayaran</button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-zinc-200 bg-white py-12 text-center text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">Tidak ada pembayaran manual yang menunggu persetujuan.</div>
        @endforelse

        @if($payments->hasPages())<div>{{ $payments->links() }}</div>@endif
    </div>
</x-layouts::app>
