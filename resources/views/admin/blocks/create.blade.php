<x-layouts::app :title="'Tambah Block - ' . $property->name">

    <div class="p-6">

        {{-- Breadcrumb --}}
        <div class="mb-5">
            <a
                href="{{ route($routePrefix.'.blocks.index', $property) }}"
                class="text-sm font-medium text-blue-600 hover:text-blue-700"
            >
                ← Kembali ke Block
            </a>
        </div>

        {{-- Header --}}
        <div class="mb-6">
            <p class="text-sm font-medium text-blue-600">
                {{ $property->name }}
            </p>

            <h1 class="mt-1 text-2xl font-bold text-gray-900">
                Tambah Block
            </h1>

            <p class="mt-1 text-sm text-gray-600">
                Tambahkan block baru pada project perumahan ini.
            </p>
        </div>

        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="mb-6 max-w-2xl rounded-lg border border-red-200 bg-red-50 p-4">

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

        {{-- Form --}}
        <div class="max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

            <form
                method="POST"
                action="{{ route($routePrefix.'.blocks.store', $property) }}"
                class="space-y-6"
            >

                @csrf

                {{-- Nama Block --}}
                <div>
                    <label
                        for="name"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Nama Block
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="Contoh: A"
                        required
                        autofocus
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >

                    <p class="mt-1 text-xs text-gray-500">
                        Contoh: A, B, C, atau Block A, Block B.
                    </p>
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
                        placeholder="Informasi tambahan mengenai block..."
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >{{ old('description') }}</textarea>
                </div>

                {{-- Info Project --}}
                <div class="rounded-lg bg-blue-50 p-4">

                    <p class="text-xs font-medium uppercase tracking-wide text-blue-600">
                        Project Perumahan
                    </p>

                    <p class="mt-1 font-semibold text-gray-900">
                        {{ $property->name }}
                    </p>

                    @if ($property->address)
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $property->address }}
                        </p>
                    @endif

                </div>

                {{-- Buttons --}}
                <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-5">

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
                        Simpan Block
                    </button>

                </div>

            </form>

        </div>

    </div>

</x-layouts::app>