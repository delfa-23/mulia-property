<x-layouts::app :title="__('Booking Marketing')">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Booking</h1>
                <p class="mt-1 text-sm text-zinc-500">Filter booking berdasarkan perumahan, kavling, customer, sales, dan status.</p>
            </div>
            <a href="{{ route('marketing.bookings.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white" wire:navigate>
                Tambah Booking
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        <form method="GET" class="rounded-xl border border-zinc-200 bg-white p-4">
            <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-5">
                <div>
                    <label class="mb-2 block text-sm font-medium">Nama Perumahan</label>
                    <select name="property_id" data-property-select class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                        <option value="">Semua Perumahan</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" @selected(request('property_id') == $property->id)>{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Customer</label>
                    <select name="customer_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                        <option value="">Semua Customer</option>
                        @foreach($customerOptions as $customer)
                            <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Kavling</label>
                    <select name="lot_id" data-lot-select disabled class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                        <option value="">Semua Kavling</option>
                        @foreach($lotOptions as $lotOption)
                            <option value="{{ $lotOption->id }}" data-property-id="{{ $lotOption->block->property_id }}" @selected(request('lot_id') == $lotOption->id)>{{ $lotOption->block->name }}/{{ $lotOption->lot_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Sales</label>
                    <select name="sales_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                        <option value="">Semua Sales</option>
                        @foreach($salesUsers as $sales)
                            <option value="{{ $sales->id }}" @selected(request('sales_id') == $sales->id)>{{ $sales->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Status</label>
                    <select name="status" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                        <option value="">Semua Status</option>
                        @foreach(['booking', 'process', 'akad', 'finish', 'cancelled'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">Filter</button>
                <a href="{{ route('marketing.bookings.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm">Reset</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-6 py-3">Tanggal</th>
                            <th class="px-6 py-3">Kavling</th>
                            <th class="px-6 py-3">Perumahan</th>
                            <th class="px-6 py-3">Customer</th>
                            <th class="px-6 py-3">Pembayaran DP</th>
                            <th class="px-6 py-3">Sales</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse($bookings as $booking)
                            <tr>
                                @php
                                    $housePrice = (float) ($booking->house_price_snapshot ?? $booking->lot->house_price);
                                    $dpPaid = (float) $booking->payments->whereIn('stage', ['dp', 'bank_settlement'])->sum('amount');
                                    $dpPaidPercentage = $housePrice > 0 ? min(100, $dpPaid / $housePrice * 100) : 0;
                                    $dpTotal = $housePrice;
                                    $dpTargetProgress = $dpTotal > 0 ? min(100, $dpPaid / $dpTotal * 100) : 0;
                                    $statusClasses = [
                                        'booking' => 'bg-amber-100 text-amber-900 ring-amber-300 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-800',
                                        'process' => 'bg-sky-100 text-sky-900 ring-sky-300 dark:bg-sky-950 dark:text-sky-200 dark:ring-sky-800',
                                        'akad' => 'bg-violet-100 text-violet-900 ring-violet-300 dark:bg-violet-950 dark:text-violet-200 dark:ring-violet-800',
                                        'finish' => 'bg-emerald-100 text-emerald-900 ring-emerald-300 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800',
                                        'cancelled' => 'bg-rose-100 text-rose-900 ring-rose-300 dark:bg-rose-950 dark:text-rose-200 dark:ring-rose-800',
                                    ];
                                @endphp
                                <td class="px-6 py-4">
                                    {{ $booking->booking_date?->format('d/m/Y') }}
                                    @if(($booking->status === 'booking' && $booking->blocking_until?->isPast()) || $booking->histories->isNotEmpty())
                                        <span role="alert" class="mt-1 block rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                            @if($booking->histories->isNotEmpty())
                                                Booking otomatis dibatalkan karena batas booking terlewati pada {{ $booking->blocking_until?->format('d/m/Y H:i') }}.
                                            @else
                                                Deadline blocking {{ $booking->customer->name }} terlewati pada {{ $booking->blocking_until->format('d/m/Y H:i') }}.
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-medium">{{ $booking->lot->block->name }}/{{ $booking->lot->lot_number }}</td>
                                <td class="px-6 py-4">{{ $booking->lot->block->property->name }}</td>
                                <td class="px-6 py-4">{{ $booking->customer->name }}</td>
                                <td class="px-6 py-4">
                                    <div class="min-w-48 space-y-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-xs font-medium text-zinc-500">DP terbayar</span>
                                            <span class="text-sm font-bold text-orange-700 dark:text-orange-400">{{ number_format($dpPaidPercentage, 2) }}%</span>
                                        </div>
                                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Rp {{ number_format($dpPaid, 0, ',', '.') }}</p>
                                        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" role="progressbar" aria-label="Progres pembayaran DP" aria-valuenow="{{ round($dpTargetProgress) }}" aria-valuemin="0" aria-valuemax="100">
                                            <div class="h-full rounded-full bg-blue-600" style="width: {{ $dpTargetProgress }}%"></div>
                                        </div>
                                        <div class="flex justify-between gap-3 text-xs text-zinc-500">
                                            <span>Target Rp {{ number_format($dpTotal, 0, ',', '.') }}</span>
                                            <span>Sisa Rp {{ number_format(max(0, $dpTotal - $dpPaid), 0, ',', '.') }}</span>
                                        </div>
                                        <span class="block text-[11px] text-zinc-400">Termasuk pembayaran dari bank</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">{{ $booking->sales?->name ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusClasses[$booking->status] ?? 'bg-zinc-100 text-zinc-800 ring-zinc-300 dark:bg-zinc-800 dark:text-zinc-200 dark:ring-zinc-600' }}">
                                        {{ ucfirst($booking->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex min-w-36 flex-col items-end gap-2">
                                        <a href="{{ route('marketing.bookings.show', $booking) }}" class="text-zinc-700" wire:navigate>Detail</a>
                                        <a href="{{ route('marketing.bookings.edit', $booking) }}" class="text-blue-600" wire:navigate>Edit Booking</a>
                                        <a href="{{ route('marketing.bookings.documents.edit', $booking) }}" class="text-blue-600" wire:navigate>Edit Berkas</a>
                                        @if($booking->status === 'cancelled')
                                            <form method="POST" action="{{ route('marketing.bookings.destroy', $booking) }}" onsubmit="return confirm('Hapus permanen booking yang cancelled ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600">Hapus</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-10 text-center text-zinc-500">Belum ada booking.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $bookings->links() }}</div>
        </div>
    </div>
</x-layouts::app>
