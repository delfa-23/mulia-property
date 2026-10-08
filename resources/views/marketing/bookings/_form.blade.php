<div>
    <label class="mb-2 block text-sm font-medium">Perumahan</label>
    <select id="booking-property" name="property_id" data-property-select required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        <option value="">Pilih Perumahan</option>
        @foreach($properties as $property)
            <option value="{{ $property->id }}" @selected(old('property_id', $booking?->lot?->block?->property_id) == $property->id)>{{ $property->name }}</option>
        @endforeach
    </select>
    @error('property_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="mb-2 block text-sm font-medium">Kavling</label>
    <select id="booking-lot" name="lot_id" data-lot-select required disabled class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        <option value="">Pilih perumahan terlebih dahulu</option>
        @foreach($lots as $lot)
            <option value="{{ $lot->id }}" data-property-id="{{ $lot->block->property_id }}" @selected(old('lot_id', $booking?->lot_id) == $lot->id)>
                {{ $lot->block->name }} / {{ $lot->lot_number }} ({{ ucfirst($lot->status) }})
            </option>
        @endforeach
    </select>
    @error('lot_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="mb-2 block text-sm font-medium">Customer</label>
    <select name="customer_id" required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        @foreach($customers as $customer)
            <option value="{{ $customer->id }}" @selected(old('customer_id', $booking?->customer_id) == $customer->id)>{{ $customer->name }}{{ $customer->nik ? ' - '.$customer->nik : '' }}</option>
        @endforeach
    </select>
    @error('customer_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="mb-2 block text-sm font-medium">Tanggal Booking</label>
        <input type="date" name="booking_date" value="{{ old('booking_date', $booking?->booking_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        @error('booking_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium">Blocking Sampai</label>
        <input type="datetime-local" name="blocking_until" value="{{ old('blocking_until', $booking?->blocking_until?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        @error('blocking_until')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="mb-2 block text-sm font-medium">Sales</label>
        <select name="sales_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
            <option value="">Pilih Sales</option>
            @foreach($salesUsers as $sales)
                <option value="{{ $sales->id }}" @selected(old('sales_id', $booking?->sales_id) == $sales->id)>{{ $sales->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium">Status</label>
        <select name="status" required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
            @foreach(['booking','process','akad','finish','cancelled'] as $status)
                <option value="{{ $status }}" @selected(old('status', $booking?->status ?? 'booking') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label class="mb-2 block text-sm font-medium">Catatan</label>
    <textarea name="notes" rows="4" class="w-full rounded-lg border border-zinc-300 px-3 py-2">{{ old('notes', $booking?->notes) }}</textarea>
</div>

