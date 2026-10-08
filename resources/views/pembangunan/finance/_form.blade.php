<div>
    <label class="mb-2 block text-sm font-medium">Project Perumahan</label>
    <select name="property_id" data-property-select required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
        <option value="">Pilih Project</option>
        @foreach($properties as $property)
            <option value="{{ $property->id }}" @selected(old('property_id', $expenseTransaction?->property_id) == $property->id)>{{ $property->name }}</option>
        @endforeach
    </select>
    @error('property_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label class="mb-2 block text-sm font-medium">Kavling (opsional)</label>
        <select name="lot_id" data-lot-select data-expense-target disabled class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
            <option value="">Pengeluaran umum project</option>
            @foreach($lots as $lot)
                <option value="{{ $lot->id }}" data-property-id="{{ $lot->block?->property_id }}" @selected(old('lot_id', $expenseTransaction?->lot_id) == $lot->id)>{{ $lot->block?->property?->name }} / {{ $lot->lot_number }}</option>
            @endforeach
        </select>
        @error('lot_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium">Fasilitas (opsional)</label>
        <select name="facility_id" data-facility-select data-expense-target disabled class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
            <option value="">Bukan pengeluaran fasilitas</option>
            @foreach($facilities as $facility)
                <option value="{{ $facility->id }}" data-property-id="{{ $facility->property_id }}" @selected(old('facility_id', $expenseTransaction?->facility_id) == $facility->id)>{{ $facility->property?->name }} / {{ $facility->name }}</option>
            @endforeach
        </select>
        @error('facility_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
<div class="grid gap-5 sm:grid-cols-2">
    <div><label class="mb-2 block text-sm font-medium">Tanggal</label><input type="date" name="transaction_date" value="{{ old('transaction_date', $expenseTransaction?->transaction_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">@error('transaction_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
    <div><label class="mb-2 block text-sm font-medium">Nominal (Rp)</label><input type="text" name="amount" value="{{ old('amount', $expenseTransaction?->amount) }}" inputmode="decimal" data-currency-input data-currency-min="0.01" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">@error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
</div>
<div><label class="mb-2 block text-sm font-medium">Kategori</label><input type="text" name="category" value="{{ old('category', $expenseTransaction?->category) }}" placeholder="Contoh: Material" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">@error('category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
<div><label class="mb-2 block text-sm font-medium">Keterangan</label><textarea name="description" rows="4" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">{{ old('description', $expenseTransaction?->description) }}</textarea>@error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
