<div>
    <label for="purpose" class="mb-2 block text-sm font-medium">Jenis RAB</label>
    <select id="purpose" name="purpose" required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        <option value="pembangunan" @selected(old('purpose', $budget?->purpose ?? 'pembangunan') === 'pembangunan')>Pembangunan</option>
        <option value="marketing" @selected(old('purpose', $budget?->purpose) === 'marketing')>Marketing / Promo</option>
    </select>
    @error('purpose')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="property_id" class="mb-2 block text-sm font-medium">Perumahan (wajib untuk RAB pembangunan)</label>
    <select id="property_id" name="property_id" data-property-select class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        <option value="">RAB umum / pilih perumahan</option>
        @foreach($properties as $property)
            <option value="{{ $property->id }}" @selected(old('property_id', $budget?->property_id) == $property->id)>{{ $property->name }}</option>
        @endforeach
    </select>
    @error('property_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="lot_id" class="mb-2 block text-sm font-medium">Kavling (opsional)</label>
        <select id="lot_id" name="lot_id" data-lot-select data-budget-target aria-describedby="budget-target-note" class="w-full rounded-lg border border-zinc-300 px-3 py-2 disabled:cursor-not-allowed disabled:bg-zinc-100">
            <option value="">RAB project umum</option>
            @foreach($lots as $lot)
                <option value="{{ $lot->id }}" data-property-id="{{ $lot->block->property_id }}" @selected(old('lot_id', $budget?->lot_id) == $lot->id)>{{ $lot->block->name }} / {{ $lot->lot_number }}</option>
            @endforeach
        </select>
        @error('lot_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="facility_id" class="mb-2 block text-sm font-medium">Fasilitas (opsional)</label>
        <select id="facility_id" name="facility_id" data-facility-select data-budget-target aria-describedby="budget-target-note" class="w-full rounded-lg border border-zinc-300 px-3 py-2 disabled:cursor-not-allowed disabled:bg-zinc-100">
            <option value="">Tanpa fasilitas tertentu</option>
            @foreach($facilities as $facility)
                <option value="{{ $facility->id }}" data-property-id="{{ $facility->property_id }}" @selected(old('facility_id', $budget?->facility_id) == $facility->id)>{{ $facility->property?->name }} / {{ $facility->name }}</option>
            @endforeach
        </select>
        @error('facility_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
<p id="budget-target-note" role="note" class="text-sm text-zinc-500 dark:text-zinc-400">
    Pilih salah satu sasaran saja: Kavling atau Fasilitas. Keduanya tidak dapat dipilih bersamaan.
</p>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="name" class="mb-2 block text-sm font-medium">Nama RAB</label>
        <input id="name" name="name" value="{{ old('name', $budget?->name) }}" required maxlength="255" placeholder="Contoh: Promo launching" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="category" class="mb-2 block text-sm font-medium">Kategori</label>
        <input id="category" name="category" value="{{ old('category', $budget?->category) }}" required maxlength="255" placeholder="Contoh: Promo / Struktur" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        @error('category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label for="amount" class="mb-2 block text-sm font-medium">Nominal (Rp)</label>
    <input id="amount" name="amount" value="{{ old('amount', $budget?->amount) }}" inputmode="decimal" data-currency-input data-currency-min="0.01" required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
    @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="period_start" class="mb-2 block text-sm font-medium">Periode Mulai</label>
        <input id="period_start" type="date" name="period_start" value="{{ old('period_start', $budget?->period_start?->format('Y-m-d')) }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        @error('period_start')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="period_end" class="mb-2 block text-sm font-medium">Periode Selesai</label>
        <input id="period_end" type="date" name="period_end" value="{{ old('period_end', $budget?->period_end?->format('Y-m-d')) }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        @error('period_end')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label for="notes" class="mb-2 block text-sm font-medium">Catatan (opsional)</label>
    <textarea id="notes" name="notes" rows="3" class="w-full rounded-lg border border-zinc-300 px-3 py-2">{{ old('notes', $budget?->notes) }}</textarea>
    @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
