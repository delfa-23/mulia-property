<x-layouts::app :title="__('Tambah RAB')">
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">Tambah RAB</h1>
                <p class="mt-1 text-sm text-zinc-500">Isi anggaran project, kavling, atau fasilitas.</p>
            </div>
            <a href="{{ route('finance.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800" wire:navigate>
                Kembali ke Keuangan
            </a>
        </div>

        <form method="POST" action="{{ route('finance.budgets.store') }}" class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            @csrf

            <div>
                <label for="property_id" class="mb-2 block text-sm font-medium">Project Perumahan</label>
                <select id="property_id" name="property_id" data-property-select required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                    <option value="">Pilih Project</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" @selected(old('property_id') == $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
                @error('property_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="lot_id" class="mb-2 block text-sm font-medium">Kavling (opsional)</label>
                    <select id="lot_id" name="lot_id" data-lot-select data-budget-target disabled class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                        <option value="">RAB untuk project umum</option>
                        @foreach($lots as $lot)
                            <option value="{{ $lot->id }}" data-property-id="{{ $lot->block->property_id }}" @selected(old('lot_id') == $lot->id)>{{ $lot->block->name }} / {{ $lot->lot_number }}</option>
                        @endforeach
                    </select>
                    @error('lot_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="facility_id" class="mb-2 block text-sm font-medium">Fasilitas (opsional)</label>
                    <select id="facility_id" name="facility_id" data-facility-select data-budget-target disabled class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                        <option value="">Tanpa fasilitas tertentu</option>
                        @foreach($facilities as $facility)
                            <option value="{{ $facility->id }}" data-property-id="{{ $facility->property_id }}" @selected(old('facility_id') == $facility->id)>{{ $facility->name }}</option>
                        @endforeach
                    </select>
                    @error('facility_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="-mt-3 text-xs text-zinc-500">Pilih salah satu sasaran: project umum, kavling, atau fasilitas.</p>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="mb-2 block text-sm font-medium">Nama RAB</label>
                    <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Contoh: Pekerjaan pondasi" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="category" class="mb-2 block text-sm font-medium">Kategori</label>
                    <input id="category" name="category" value="{{ old('category') }}" required maxlength="255" placeholder="Contoh: Infrastruktur" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                    @error('category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="amount" class="mb-2 block text-sm font-medium">Nominal (Rp)</label>
                <input id="amount" name="amount" value="{{ old('amount') }}" inputmode="decimal" data-currency-input data-currency-min="0.01" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="period_start" class="mb-2 block text-sm font-medium">Periode Mulai (opsional)</label>
                    <input id="period_start" type="date" name="period_start" value="{{ old('period_start') }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                    @error('period_start')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="period_end" class="mb-2 block text-sm font-medium">Periode Selesai (opsional)</label>
                    <input id="period_end" type="date" name="period_end" value="{{ old('period_end') }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                    @error('period_end')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="notes" class="mb-2 block text-sm font-medium">Catatan (opsional)</label>
                <textarea id="notes" name="notes" rows="4" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">{{ old('notes') }}</textarea>
                @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                <a href="{{ route('finance.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium dark:border-zinc-600">Batal</a>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Simpan RAB</button>
            </div>
        </form>
    </div>
</x-layouts::app>