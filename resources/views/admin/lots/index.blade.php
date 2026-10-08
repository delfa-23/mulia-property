<x-layouts::app :title="'Kavling - ' . $block->name">

    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a
                    href="{{ route($routePrefix.'.blocks.index', $property) }}"
                    class="text-sm font-medium text-gray-500 hover:text-gray-700"
                >
                    ← Kembali ke Block
                </a>

                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    Kavling {{ $block->name }}
                </h1>

                <p class="mt-1 text-sm text-gray-500">
                    {{ $property->name }}
                </p>
            </div>

            <a
                href="{{ route($routePrefix.'.blocks.lots.create', [$property, $block]) }}"
                class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
            >
                + Tambah Kavling
            </a>
        </div>

        {{-- Search --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form
                method="GET"
                action="{{ route($routePrefix.'.blocks.lots.index', [$property, $block]) }}"
                class="flex flex-col gap-3 sm:flex-row"
            >
                <select
                    name="lot_number"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                >
                    <option value="">Semua kavling</option>
                    @foreach($lotOptions as $lotOption)
                        <option value="{{ $lotOption->lot_number }}" @selected(request('lot_number') === $lotOption->lot_number)>
                            {{ $lotOption->lot_number }}
                        </option>
                    @endforeach
                </select>

                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari nomor kavling..."
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                >

                <button
                    type="submit"
                    class="rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800"
                >
                    Cari
                </button>
            </form>
        </div>

        {{-- Flash Message --}}
        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        {{-- Lots --}}
        @if($lots->count())

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">

                @foreach($lots as $lot)

                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">

                        <div class="flex items-start justify-between gap-4">

                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                    Kavling
                                </p>

                                <h2 class="mt-1 text-xl font-bold text-gray-900">
                                    {{ $lot->lot_number }}
                                </h2>
                            </div>

                            @php
                                $statusClasses = [
                                    'available' => 'bg-green-100 text-green-700',
                                    'blocked' => 'bg-yellow-100 text-yellow-700',
                                    'booked' => 'bg-blue-100 text-blue-700',
                                    'process' => 'bg-purple-100 text-purple-700',
                                    'akad' => 'bg-orange-100 text-orange-700',
                                    'finish' => 'bg-gray-100 text-gray-700',
                                    'cancelled' => 'bg-red-100 text-red-700',
                                ];

                                $statusLabels = [
                                    'available' => 'Tersedia',
                                    'blocked' => 'Blocked',
                                    'booked' => 'Booking',
                                    'process' => 'Proses',
                                    'akad' => 'Akad',
                                    'finish' => 'Finish',
                                    'cancelled' => 'Dibatalkan',
                                ];
                            @endphp

                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$lot->status] ?? 'bg-gray-100 text-gray-700' }}"
                            >
                                {{ $statusLabels[$lot->status] ?? ucfirst($lot->status) }}
                            </span>

                        </div>

                        <div class="mt-5 space-y-3">

                            <div>
                                <p class="text-xs text-gray-400">
                                    Harga Rumah
                                </p>

                                <p class="mt-1 text-lg font-bold text-gray-900">
                                    Rp {{ number_format($lot->house_price, 0, ',', '.') }}
                                </p>
                            </div>

                            @if($lot->notes)
                                <div>
                                    <p class="text-xs text-gray-400">
                                        Catatan
                                    </p>

                                    <p class="mt-1 text-sm text-gray-600">
                                        {{ $lot->notes }}
                                    </p>
                                </div>
                            @endif

                        </div>

                        <div class="mt-5 flex gap-2 border-t border-gray-100 pt-4">

                            <a
                                href="{{ route($routePrefix.'.blocks.lots.edit', [$property, $block, $lot]) }}"
                                class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Edit
                            </a>

                            <form
                                action="{{ route($routePrefix.'.blocks.lots.destroy', [$property, $block, $lot]) }}"
                                method="POST"
                                onsubmit="return confirm('Yakin ingin menghapus kavling ini?')"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                                >
                                    Hapus
                                </button>
                            </form>

                        </div>

                    </div>

                @endforeach

            </div>

            <div>
                {{ $lots->links() }}
            </div>

        @else

            <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">

                <div class="mx-auto max-w-md">

                    <h2 class="text-lg font-semibold text-gray-900">
                        Belum ada kavling
                    </h2>

                    <p class="mt-2 text-sm text-gray-500">
                        Tambahkan kavling untuk Block {{ $block->name }}.
                    </p>

                    <a
                        href="{{ route($routePrefix.'.blocks.lots.create', [$property, $block]) }}"
                        class="mt-5 inline-flex rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        + Tambah Kavling
                    </a>

                </div>

            </div>

        @endif

    </div>

</x-layouts::app>