<x-layouts::app :title="'Edit Block - ' . $block->name">

    <div class="mx-auto max-w-3xl space-y-6">

        <div>
            <a
                href="{{ route($routePrefix.'.blocks.index', $property) }}"
                class="text-sm font-medium text-gray-500 hover:text-gray-700"
            >
                ← Kembali ke Block
            </a>

            <h1 class="mt-2 text-2xl font-bold text-gray-900">
                Edit Block
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                {{ $property->name }}
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

            <form
                method="POST"
                action="{{ route($routePrefix.'.blocks.update', [$property, $block]) }}"
                class="space-y-5"
            >
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Nama Block
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="{{ old('name', $block->name) }}"
                        placeholder="Contoh: Block A"
                        required
                        class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    >

                    @error('name')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Deskripsi
                    </label>

                    <textarea
                        name="description"
                        rows="4"
                        placeholder="Deskripsi block..."
                        class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    >{{ old('description', $block->description) }}</textarea>

                    @error('description')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex justify-end gap-3 border-t border-gray-100 pt-5">

                    <a
                        href="{{ route($routePrefix.'.blocks.index', $property) }}"
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