<x-layouts::app :title="__('Checklist Berkas')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Checklist Berkas</h1>
            <p class="mt-1 text-sm text-zinc-500">Kelola proses berkas dari booking yang aktif.</p>
        </div>

        <form method="GET" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
            <select name="property_id" data-property-select class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                <option value="">Semua perumahan</option>
                @foreach($properties as $property)
                    <option value="{{ $property->id }}" @selected(request('property_id') == $property->id)>{{ $property->name }}</option>
                @endforeach
            </select>
            <select name="lot_id" data-lot-select disabled class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                <option value="">Semua kavling</option>
                @foreach($lotOptions as $lotOption)
                    <option value="{{ $lotOption->id }}" data-property-id="{{ $lotOption->block->property_id }}" @selected(request('lot_id') == $lotOption->id)>{{ $lotOption->block->name }}/{{ $lotOption->lot_number }}</option>
                @endforeach
            </select>
            <select name="status" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                <option value="">Semua status</option>
                @foreach(['booking', 'process', 'akad', 'finish'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <input name="search" value="{{ request('search') }}" placeholder="Cari nama customer" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-3 py-2">
                <button class="rounded-lg bg-zinc-900 px-4 py-2 text-sm text-white">Filter</button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-6 py-3">Customer</th>
                            <th class="px-6 py-3">Kavling</th>
                            <th class="px-6 py-3">Berkas</th>
                            <th class="px-6 py-3">BI</th>
                            <th class="px-6 py-3">Bank</th>
                            <th class="px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse($bookings as $booking)
                            <tr>
                                <td class="px-6 py-4 font-medium">
                                    {{ $booking->customer->name }}
                                    @if(in_array($booking->akadSchedule?->status, ['scheduled', 'rescheduled'], true) && $booking->akadSchedule?->scheduled_at?->isPast())
                                        <span role="status" class="mt-1 block rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800">
                                            Jadwal akad {{ $booking->customer->name }} terlewati pada {{ $booking->akadSchedule->scheduled_at->format('d/m/Y H:i') }}.
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">{{ $booking->lot->block->name }}/{{ $booking->lot->lot_number }}</td>
                                <td class="px-6 py-4">{{ $booking->documentProcess?->status ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $booking->documentProcess?->bi_checking_status ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $booking->bankProcess?->status ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('pemberkasan.bookings.show', $booking) }}" class="text-blue-600" wire:navigate>Kelola</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-10 text-center text-zinc-500">Belum ada booking.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $bookings->links() }}</div>
        </div>
    </div>
</x-layouts::app>
