<x-layouts::app :title="'Edit Property'">

    <div class="p-6">

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">
                Edit Property
            </h1>

            <p class="mt-1 text-sm text-gray-600">
                Perbarui informasi property / perumahan.
            </p>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
                <div class="font-semibold text-red-800">
                    Ada kesalahan:
                </div>

                <ul class="mt-2 list-disc pl-5 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

            <form
                method="POST"
                action="{{ route($routePrefix.'.update', $property) }}"
                class="space-y-6"
            >
                @csrf
                @method('PUT')

                {{-- Nama Property --}}
                <div>
                    <label
                        for="name"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Nama Property / Perumahan
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name', $property->name) }}"
                        required
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                </div>

                {{-- Alamat --}}
                <div>
                    <label
                        for="address"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Alamat
                    </label>

                    <textarea
                        id="address"
                        name="address"
                        rows="3"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >{{ old('address', $property->address) }}</textarea>
                </div>

                {{-- Deskripsi --}}
                <div>
                    <label
                        for="description"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Deskripsi
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >{{ old('description', $property->description) }}</textarea>
                </div>

                {{-- Buttons --}}
                <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-5">

                    <a
                        href="{{ route($routePrefix.'.index') }}"
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