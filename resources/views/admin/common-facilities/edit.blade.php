<x-layouts::app :title="__('Edit Fasilitas Umum')">

    <div class="mx-auto max-w-3xl space-y-6">

        <div>
            <h1 class="text-2xl font-semibold">
                Edit Fasilitas Umum
            </h1>

            <p class="mt-1 text-sm text-zinc-500">
                Perbarui data fasilitas umum.
            </p>
        </div>

        <form
            method="POST"
            action="{{ route('admin.common-facilities.update', $commonFacility) }}"
            class="space-y-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900"
        >
            @csrf
            @method('PUT')

            <div>
                <label class="mb-2 block text-sm font-medium">
                    Project Perumahan
                </label>

                <select
                    name="property_id"
                    required
                    class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                >
                    <option value="">Pilih Project</option>

                    @foreach($properties as $property)
                        <option
                            value="{{ $property->id }}"
                            @selected(old('property_id', $commonFacility->property_id) == $property->id)
                        >
                            {{ $property->name }}
                        </option>
                    @endforeach
                </select>

                @error('property_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium">
                    Nama Fasilitas
                </label>

                <input
                    type="text"
                    name="name"
                    value="{{ old('name', $commonFacility->name) }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                >

                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium">
                    Deskripsi
                </label>

                <textarea
                    name="description"
                    rows="4"
                    class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                >{{ old('description', $commonFacility->description) }}</textarea>

                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium">
                    Anggaran (Rp)
                </label>

                <input
                    type="text"
                    name="budget"
                    value="{{ old('budget', $commonFacility->budget) }}"
                    inputmode="decimal"
                    data-currency-input
                    data-currency-min="0"
                    data-currency-step="0.01"
                    required
                    class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                >

                @error('budget')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-700">
                Realisasi dan status dikelola oleh tim Pembangunan dari menu Fasilitas Umum.
            </div>

            <div class="flex justify-end gap-3">
                <flux:button
                    variant="ghost"
                    :href="route('admin.common-facilities.index')"
                    wire:navigate
                >
                    Batal
                </flux:button>

                <flux:button type="submit" variant="primary">
                    Simpan Perubahan
                </flux:button>
            </div>

        </form>

    </div>

</x-layouts::app>