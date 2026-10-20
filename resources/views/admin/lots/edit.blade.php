<x-layouts::app :title="'Edit Kavling ' . $lot->lot_number">

    <div class="mx-auto max-w-3xl space-y-6">

        <div>
            <a
                href="{{ $backUrl ?? route($routePrefix.'.blocks.lots.index', [$property, $block]) }}"
                class="text-sm font-medium text-gray-500 hover:text-gray-700"
            >
                ← Kembali ke Kavling
            </a>

            <h1 class="mt-2 text-2xl font-bold text-gray-900">
                Edit Kavling {{ $lot->lot_number }}
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                {{ $property->name }} — {{ $block->name }}
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

            <form
                method="POST"
                action="{{ $updateUrl ?? route($routePrefix.'.blocks.lots.update', [$property, $block, $lot]) }}"
                enctype="multipart/form-data"
                class="space-y-5"
            >
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Nomor Kavling
                    </label>

                    <div class="mt-1 flex overflow-hidden rounded-lg border border-gray-300 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
                        <span class="inline-flex items-center border-r border-gray-300 bg-gray-50 px-3 text-sm text-gray-600">
                            {{ $block->name }}-
                        </span>
                        <input
                            type="text"
                            name="lot_number"
                            value="{{ $lotNumberSuffix }}"
                            inputmode="numeric"
                            pattern="[0-9]+"
                            required
                            class="block min-w-0 flex-1 border-0 focus:ring-0"
                        >
                    </div>

                    @error('lot_number')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Harga Rumah (Rp)
                    </label>

                    <input
                        type="text"
                        name="house_price"
                        value="{{ old('house_price', $lot->house_price) }}"
                        inputmode="numeric"
                        data-currency-input
                        data-currency-min="0"
                        data-currency-step="1000"
                        required
                        class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    >

                    @error('house_price')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Foto Kavling</label>
                    @if($lot->imageGallery())
                        <div class="mb-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach($lot->imageGallery() as $photo)
                                <div data-lot-saved-image class="relative">
                                    <img src="{{ $photo['url'] }}" alt="Foto {{ $lot->lot_number }}" class="aspect-video w-full rounded-lg border border-gray-200 object-cover">
                                    <input type="checkbox" name="remove_images[]" value="{{ $photo['path'] }}" data-remove-lot-saved-image hidden>
                                    <button type="button" data-toggle-lot-saved-image class="absolute right-2 top-2 rounded-full bg-white px-2 py-1 text-sm font-bold shadow" aria-label="Hapus foto">-</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div data-lot-image-inputs class="space-y-2">
                        <div data-lot-image-field class="flex gap-2">
                            <input
                                type="file"
                                name="images[]"
                                accept="image/jpeg,image/png,image/webp"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                            >
                            <button type="button" data-remove-lot-image-field class="rounded-lg border border-gray-300 px-3 text-lg" aria-label="Hapus pilihan foto">-</button>
                        </div>
                    </div>
                    <button type="button" data-add-lot-image-field class="mt-2 rounded-lg border border-gray-300 px-3 py-1 text-sm font-medium">+ Tambah foto</button>
                    <p class="mt-1 text-xs text-gray-500">JPG, PNG, atau WebP; maksimal 10 MB per foto.</p>
                    @foreach($errors->get('images.*') as $imageErrors)
                        @foreach($imageErrors as $message)
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @endforeach
                    @endforeach
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Status
                    </label>

                    <select
                        name="status"
                        required
                        class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="available" @selected(old('status', $lot->status) === 'available')>
                            Tersedia
                        </option>
                        <option value="blocked" @selected(old('status', $lot->status) === 'blocked')>
                            Blocked
                        </option>
                        <option value="booked" @selected(old('status', $lot->status) === 'booked')>
                            Booking
                        </option>
                        <option value="process" @selected(old('status', $lot->status) === 'process')>
                            Proses
                        </option>
                        <option value="akad" @selected(old('status', $lot->status) === 'akad')>
                            Akad
                        </option>
                        <option value="finish" @selected(old('status', $lot->status) === 'finish')>
                            Finish
                        </option>
                        <option value="cancelled" @selected(old('status', $lot->status) === 'cancelled')>
                            Dibatalkan
                        </option>
                    </select>

                    @error('status')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Catatan
                    </label>

                    <textarea
                        name="notes"
                        rows="4"
                        class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    >{{ old('notes', $lot->notes) }}</textarea>

                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-3 border-t border-gray-100 pt-5">

                    <a
                        href="{{ $backUrl ?? route($routePrefix.'.blocks.lots.index', [$property, $block]) }}"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Simpan Perubahan
                    </button>

                </div>

            </form>

        </div>

    </div>

</x-layouts::app>