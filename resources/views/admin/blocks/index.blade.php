<x-layouts::app :title="'Block - ' . $property->name">

    <div class="p-6">

        {{-- Breadcrumb --}}
        <div class="mb-5">
            <a
                href="{{ route($routePrefix.'.index') }}"
                class="text-sm font-medium text-blue-600 hover:text-blue-700"
            >
                ← Project Perumahan
            </a>
        </div>

        {{-- Header --}}
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">

            <div>
                <p class="text-sm font-medium text-blue-600">
                    Project Perumahan
                </p>

                <h1 class="mt-1 text-2xl font-bold text-gray-900">
                    {{ $property->name }}
                </h1>

                <p class="mt-1 text-sm text-gray-600">
                    Kelola block / blok pada project ini.
                </p>
            </div>

            <a
                href="{{ route($routePrefix.'.blocks.create', $property) }}"
                class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            >
                + Tambah Block
            </a>

        </div>

        {{-- Flash --}}
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

        {{-- Search --}}
        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">

            <form
                method="GET"
                action="{{ route($routePrefix.'.blocks.index', $property) }}"
                class="flex flex-col gap-3 md:flex-row"
            >

                <select
                    name="block_id"
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                >
                    <option value="">Semua block</option>
                    @foreach($blockOptions as $blockOption)
                        <option value="{{ $blockOption->id }}" @selected(request('block_id') == $blockOption->id)>
                            {{ $blockOption->name }}
                        </option>
                    @endforeach
                </select>

                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari block..."
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                >

                <button
                    type="submit"
                    class="rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800"
                >
                    Cari
                </button>

                @if(request('search') || request('block_id'))
                    <a
                        href="{{ route($routePrefix.'.blocks.index', $property) }}"
                        class="rounded-lg border border-gray-300 px-5 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Reset
                    </a>
                @endif

            </form>

        </div>

        {{-- Blocks --}}
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">

            @forelse ($blocks as $block)

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">

                    <div class="p-5">

                        <div class="flex items-start justify-between">

                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                    Block
                                </p>

                                <h2 class="mt-1 text-xl font-bold text-gray-900">
                                    {{ $block->name }}
                                </h2>
                            </div>

                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                                {{ $block->lots_count }} Kavling
                            </span>

                        </div>

                        <p class="mt-4 text-sm text-gray-500">
                            {{ $block->description ?: 'Tidak ada deskripsi.' }}
                        </p>

                    </div>

                    <div class="flex items-center justify-between border-t border-gray-100 p-4">

                        <a
                            href="{{ route($routePrefix.'.blocks.lots.index', [$property, $block]) }}"
                            class="text-sm font-semibold text-blue-600 hover:text-blue-700"
                        >
                            Lihat Kavling →
                        </a>

                        <div class="flex gap-2">

                            <a
                                href="{{ route($routePrefix.'.blocks.edit', [$property, $block]) }}"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Edit
                            </a>

                            <form
                                method="POST"
                                action="{{ route($routePrefix.'.blocks.destroy', [$property, $block]) }}"
                                onsubmit="return confirm('Yakin ingin menghapus block ini?')"
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
                        🏘️
                    </div>

                    <h2 class="mt-4 text-lg font-semibold text-gray-900">
                        Belum ada Block
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Tambahkan block pertama untuk project
                        {{ $property->name }}.
                    </p>

                    <a
                        href="{{ route($routePrefix.'.blocks.create', $property) }}"
                        class="mt-5 inline-flex rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        + Tambah Block
                    </a>

                </div>

            @endforelse

        </div>

        @if ($blocks->hasPages())
            <div class="mt-6">
                {{ $blocks->links() }}
            </div>
        @endif

    </div>

</x-layouts::app>