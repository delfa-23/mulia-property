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
                            <th class="px-6 py-3">Sales</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse($bookings as $booking)
                            <tr>
                                <td class="px-6 py-4">
                                    {{ $booking->booking_date?->format('d/m/Y') }}
                                    @if($booking->status === 'booking' && $booking->blocking_until?->isPast())
                                        <span role="status" class="mt-1 block rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                            Deadline blocking {{ $booking->customer->name }} terlewati pada {{ $booking->blocking_until->format('d/m/Y H:i') }}.
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-medium">{{ $booking->lot->block->name }}/{{ $booking->lot->lot_number }}</td>
                                <td class="px-6 py-4">{{ $booking->lot->block->property->name }}</td>
                                <td class="px-6 py-4">{{ $booking->customer->name }}</td>
                                <td class="px-6 py-4">{{ $booking->sales?->name ?? '-' }}</td>
                                <td class="px-6 py-4">{{ ucfirst($booking->status) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('marketing.bookings.show', $booking) }}" class="mr-3 text-zinc-700" wire:navigate>Detail</a>
                                    <a href="{{ route('marketing.bookings.edit', $booking) }}" class="text-blue-600" wire:navigate>Edit</a>
                                    @if($booking->status === 'cancelled')
                                        <form method="POST" action="{{ route('marketing.bookings.destroy', $booking) }}" class="ml-3 inline" onsubmit="return confirm('Hapus permanen booking yang cancelled ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600">Hapus</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-10 text-center text-zinc-500">Belum ada booking.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $bookings->links() }}</div>
        </div>
    </div>
</x-layouts::app>
