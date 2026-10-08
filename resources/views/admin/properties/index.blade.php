<x-layouts::app :title="'Project Perumahan'">

    <div class="p-6">

        {{-- Header --}}
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">

            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    Project Perumahan
                </h1>

                <p class="mt-1 text-sm text-gray-600">
                    Kelola project perumahan, block, dan data kavling.
                </p>
            </div>

            <a
                href="{{ route($routePrefix.'.create') }}"
                class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            >
                + Buat Project Perumahan
            </a>

        </div>

        {{-- Search --}}
        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">

            <form
                method="GET"
                action="{{ route($routePrefix.'.index') }}"
                class="flex flex-col gap-3 md:flex-row"
            >

                <div class="flex-1">

                    <label for="property_id" class="mb-2 block text-sm font-medium text-gray-700">
                        Pilih perumahan
                    </label>

                    <select
                        id="property_id"
                        name="property_id"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">Semua perumahan</option>
                        @foreach($propertyOptions as $propertyOption)
                            <option value="{{ $propertyOption->id }}" @selected(request('property_id') == $propertyOption->id)>
                                {{ $propertyOption->name }}
                            </option>
                        @endforeach
                    </select>

                    <label
                        for="search"
                        class="sr-only"
                    >
                        Cari perumahan
                    </label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Cari nama perumahan..."
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                    >

                </div>

                <button
                    type="submit"
                    class="rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800"
                >
                    Cari
                </button>

                @if(request('search') || request('property_id'))
                    <a
                        href="{{ route($routePrefix.'.index') }}"
                        class="rounded-lg border border-gray-300 px-5 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Reset
                    </a>
                @endif

            </form>

        </div>

        {{-- Flash Message --}}
        @if (session('success'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Project Cards --}}
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">

            @forelse ($properties as $property)

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md">

                    {{-- Card Header --}}
                    <div class="border-b border-gray-100 p-5">

                        <div class="flex items-start justify-between gap-3">

                            <div>
                                <h2 class="text-lg font-bold text-gray-900">
                                    {{ $property->name }}
                                </h2>

                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $property->address ?: 'Alamat belum diisi' }}
                                </p>
                            </div>

                            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                Project
                            </span>

                        </div>

                    </div>

                    {{-- Statistics --}}
                    <div class="grid grid-cols-2 divide-x border-b border-gray-100">

                        <div class="p-4 text-center">
                            <p class="text-2xl font-bold text-gray-900">
                                {{ $property->blocks_count }}
                            </p>

                            <p class="mt-1 text-xs text-gray-500">
                                Block
                            </p>
                        </div>

                        <div class="p-4 text-center">
                            <p class="text-2xl font-bold text-gray-900">
                                {{ $property->lots_count }}
                            </p>

                            <p class="mt-1 text-xs text-gray-500">
                                Kavling
                            </p>
                        </div>

                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center justify-between p-4">

                        <a
                            href="{{ route($routePrefix.'.blocks.index', $property) }}"
                            class="text-sm font-semibold text-blue-600 hover:text-blue-700"
                        >
                            Lihat Project →
                        </a>

                        <div class="flex gap-2">

                            <a
                                href="{{ route($routePrefix.'.edit', $property) }}"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Edit
                            </a>

                            <form
                                method="POST"
                                action="{{ route($routePrefix.'.destroy', $property) }}"
                                onsubmit="return confirm('Yakin ingin menghapus project ini?')"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="rounded-lg border border-red-200 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50"
                                >
                                    Hapus
                                </button>
                            </form>

                        </div>

                    </div>

                </div>

            @empty

                <div class="col-span-full rounded-xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">

                    <div class="text-4xl">
                        🏠
                    </div>

                    <h2 class="mt-4 text-lg font-semibold text-gray-900">
                        Belum ada project perumahan
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Buat project perumahan pertama untuk mulai mengatur block dan kavling.
                    </p>

                    <a
                        href="{{ route($routePrefix.'.create') }}"
                        class="mt-5 inline-flex rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        + Buat Project
                    </a>

                </div>

            @endforelse

        </div>

        {{-- Pagination --}}
        @if ($properties->hasPages())
            <div class="mt-6">
                {{ $properties->links() }}
            </div>
        @endif

    </div>

</x-layouts::app>