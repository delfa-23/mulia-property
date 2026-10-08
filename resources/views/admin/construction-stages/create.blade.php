<x-layouts::app :title="'Tambah Tahapan Pembangunan'">

    <div class="mx-auto max-w-2xl space-y-6">

        <div>
            <a
                href="{{ route('admin.construction-stages.index') }}"
                class="text-sm font-medium text-gray-500 hover:text-gray-700"
            >
                ← Kembali
            </a>

            <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                Tambah Tahapan Pembangunan
            </h1>
        </div>


        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">

            <form
                method="POST"
                action="{{ route('admin.construction-stages.store') }}"
                class="space-y-5"
            >

                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                        Nama Tahapan
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="Contoh: Pondasi"
                        required
                        class="mt-1 block w-full rounded-lg border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                    >

                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>


                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                        Urutan
                    </label>

                    <input
                        type="number"
                        name="order"
                        value="{{ old('order', 1) }}"
                        min="0"
                        required
                        class="mt-1 block w-full rounded-lg border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                    >

                    @error('order')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                            Progress Minimum (%)
                        </label>

                        <input
                            type="number"
                            name="min_progress"
                            value="{{ old('min_progress', 0) }}"
                            min="0"
                            max="100"
                            step="0.01"
                            required
                            class="mt-1 block w-full rounded-lg border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                        >

                        @error('min_progress')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                            Progress Maksimum (%)
                        </label>

                        <input
                            type="number"
                            name="max_progress"
                            value="{{ old('max_progress', 100) }}"
                            min="0"
                            max="100"
                            step="0.01"
                            required
                            class="mt-1 block w-full rounded-lg border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                        >

                        @error('max_progress')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>


                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">
                        Deskripsi
                    </label>

                    <textarea
                        name="description"
                        rows="4"
                        class="mt-1 block w-full rounded-lg border-gray-300 dark:border-zinc-700 dark:bg-zinc-800"
                    >{{ old('description') }}</textarea>
                </div>


                <div class="flex justify-end gap-3 border-t border-gray-100 pt-5">

                    <a
                        href="{{ route('admin.construction-stages.index') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Simpan
                    </button>

                </div>

            </form>

        </div>

    </div>

</x-layouts::app>