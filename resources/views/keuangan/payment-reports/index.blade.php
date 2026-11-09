<x-layouts::app :title="'Laporan Pembayaran'">
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-[#10233D] dark:text-white">Laporan Pembayaran</h1>
                <p class="mt-1 text-sm text-zinc-500">Lihat penerimaan dan sisa pembayaran setiap kavling.</p>
            </div>
            <div class="rounded-xl bg-emerald-50 px-5 py-3 text-right dark:bg-emerald-950/30">
                <p class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Masuk hari ini</p>
                <p class="text-lg font-bold text-emerald-800 dark:text-emerald-200">Rp {{ number_format((float) $approvedToday, 0, ',', '.') }}</p>
            </div>
        </div>

        <form method="GET" class="flex gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari customer atau kavling" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
            <button class="rounded-lg bg-[#10233D] px-4 py-2 font-semibold text-white">Cari</button>
        </form>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-left">Rumah / Kavling</th>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-right">Sudah Dibayar</th>
                            <th class="px-4 py-3 text-right">Sisa Pembayaran</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($bookings as $row)
                            @php($booking = $row['booking'])
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold">{{ $booking->lot->block->property->name ?? '-' }}</p>
                                    <p class="text-zinc-500">Kavling {{ $booking->lot->lot_number }}</p>
                                </td>
                                <td class="px-4 py-4">{{ $booking->customer->name ?? '-' }}</td>
                                <td class="px-4 py-4 text-right font-semibold">Rp {{ number_format((float) $row['summary']['total_paid'], 0, ',', '.') }}</td>
                                <td class="px-4 py-4 text-right font-semibold text-orange-700">Rp {{ number_format((float) $row['remaining'], 0, ',', '.') }}</td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('keuangan.payment-reports.show', $booking) }}" class="rounded-lg border border-[#10233D] px-3 py-2 text-sm font-semibold text-[#10233D] hover:bg-[#10233D] hover:text-white">Lihat Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-zinc-500">Belum ada booking pembayaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($bookings->hasPages())<div class="border-t px-4 py-3">{{ $bookings->links() }}</div>@endif
        </div>
    </div>
</x-layouts::app>
