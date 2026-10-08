<x-layouts::app :title="__('Ketersediaan Kavling')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Ketersediaan Kavling</h1>
            <p class="mt-1 text-sm text-zinc-500">Filter project, block, nomor kavling, status, dan harga.</p>
        </div>

        <form method="GET" class="rounded-xl border border-zinc-200 bg-white p-4">
            <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-4">
                <div><label class="mb-2 block text-sm font-medium">Nama Perumahan</label><select name="property_id" data-property-select class="w-full rounded-lg border border-zinc-300 px-3 py-2"><option value="">Semua Perumahan</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected(request('property_id') == $property->id)>{{ $property->name }}</option>@endforeach</select></div>
                <div><label class="mb-2 block text-sm font-medium">Kavling</label><select name="lot_id" data-lot-select disabled class="w-full rounded-lg border border-zinc-300 px-3 py-2"><option value="">Semua Kavling</option>@foreach($lotOptions as $lotOption)<option value="{{ $lotOption->id }}" data-property-id="{{ $lotOption->block->property_id }}" @selected(request('lot_id') == $lotOption->id)>{{ $lotOption->block->name }}/{{ $lotOption->lot_number }}</option>@endforeach</select></div>
                <div><label class="mb-2 block text-sm font-medium">Status</label><select name="status" class="w-full rounded-lg border border-zinc-300 px-3 py-2"><option value="">Semua Status</option>@foreach(['available','blocked','booked','process','akad','finish','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
                <div><label class="mb-2 block text-sm font-medium">Harga Minimal (Rp)</label><input type="text" name="min_price" value="{{ request('min_price') }}" inputmode="numeric" data-currency-input data-currency-min="0" data-currency-step="1" class="w-full rounded-lg border border-zinc-300 px-3 py-2"></div>
                <div><label class="mb-2 block text-sm font-medium">Harga Maksimal (Rp)</label><input type="text" name="max_price" value="{{ request('max_price') }}" inputmode="numeric" data-currency-input data-currency-min="0" data-currency-step="1" class="w-full rounded-lg border border-zinc-300 px-3 py-2"></div>
                <div><label class="mb-2 block text-sm font-medium">Pencarian Umum</label><input name="search" value="{{ request('search') }}" placeholder="Project, block, atau kavling" class="w-full rounded-lg border border-zinc-300 px-3 py-2"></div>
                <div class="flex items-end gap-2"><button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">Filter</button><a href="{{ route('marketing.lots.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm">Reset</a></div>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-zinc-50"><tr><th class="px-6 py-3">Perumahan</th><th class="px-6 py-3">Kavling</th><th class="px-6 py-3">Harga</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Customer</th></tr></thead><tbody class="divide-y divide-zinc-200">@forelse($lots as $lot)@php($activeBooking = $lot->bookings->first())<tr><td class="px-6 py-4">{{ $lot->block->property->name }}</td><td class="px-6 py-4 font-medium">{{ $lot->block->name }}/{{ $lot->lot_number }}</td><td class="px-6 py-4">Rp {{ number_format((float) $lot->house_price, 0, ',', '.') }}</td><td class="px-6 py-4">{{ ucfirst($lot->status) }}</td><td class="px-6 py-4">{{ $activeBooking?->customer?->name ?? '-' }}</td></tr>@empty<tr><td colspan="5" class="px-6 py-10 text-center text-zinc-500">Belum ada data kavling.</td></tr>@endforelse</tbody></table></div><div class="px-6 py-4">{{ $lots->links() }}</div></div>
    </div>
</x-layouts::app>
