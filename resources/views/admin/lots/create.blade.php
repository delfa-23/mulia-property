<x-layouts::app :title="'Tambah Kavling'">

    <div class="mx-auto max-w-3xl space-y-6">

        <div>
            <a
                href="{{ route($routePrefix.'.blocks.lots.index', [$property, $block]) }}"
                class="text-sm font-medium text-gray-500 hover:text-gray-700"
            >
                ← Kembali ke Kavling
            </a>

            <h1 class="mt-2 text-2xl font-bold text-gray-900">
                Tambah Kavling
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                {{ $property->name }} — {{ $block->name }}
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

            <form
                method="POST"
                action="{{ route($routePrefix.'.blocks.lots.store', [$property, $block]) }}"
                class="space-y-5"
            >
                @csrf

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
                            placeholder="01"
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
                        value="{{ old('house_price') }}"
                        inputmode="numeric"
                        data-currency-input
                        data-currency-min="0"
                        data-currency-step="1000"
                        placeholder="Contoh: 350000000"
                        required
                        class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    >

                    @error('house_price')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
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
                        <option value="available" @selected(old('status', 'available') === 'available')>
                            Tersedia
                        </option>
                        <option value="blocked" @selected(old('status') === 'blocked')>
                            Blocked
                        </option>
                        <option value="booked" @selected(old('status') === 'booked')>
                            Booking
                        </option>
                        <option value="process" @selected(old('status') === 'process')>
                            Proses
                        </option>
                        <option value="akad" @selected(old('status') === 'akad')>
                            Akad
                        </option>
                        <option value="finish" @selected(old('status') === 'finish')>
                            Finish
                        </option>
                        <option value="cancelled" @selected(old('status') === 'cancelled')>
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
                        placeholder="Catatan tambahan..."
                        class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    >{{ old('notes') }}</textarea>

                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-3 border-t border-gray-100 pt-5">

                    <a
                        href="{{ route($routePrefix.'.blocks.lots.index', [$property, $block]) }}"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Simpan Kavling
                    </button>

                </div>

            </form>

        </div>

    </div>

</x-layouts::app>