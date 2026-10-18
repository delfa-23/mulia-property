<section id="site-plan-editor" class="mt-8 space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Langkah 3 · Pemetaan posisi</p>
            <h2 class="mt-1 text-lg font-semibold text-gray-900">Tandai Area Kavling</h2>
            <p class="mt-1 max-w-3xl text-sm text-gray-600">
                Pastikan langkah 2 sudah lengkap. Setiap kavling harus ditandai satu per satu agar semua area bisa diberi warna status yang benar pada dashboard.
            </p>
        </div>
        <span class="inline-flex w-fit shrink-0 items-center rounded-full bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-800">
            {{ $mappedLotCount }} / {{ $lots->count() }} selesai
        </span>
    </div>

    @if($blocks->isNotEmpty())
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <h3 class="text-sm font-semibold text-slate-800">Daftar kavling yang harus dipetakan</h3>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($blocks as $block)
                    <div class="flex items-center justify-between gap-3 rounded-md bg-white px-3 py-2 text-sm">
                        <span class="font-medium text-slate-700">Blok {{ $block->name }}</span>
                        <span class="{{ $block->lots_count > 0 && $mappedLotCountsByBlock->get($block->id, 0) === $block->lots_count ? 'text-emerald-700' : 'text-slate-500' }}">
                            {{ $mappedLotCountsByBlock->get($block->id, 0) }} / {{ $block->lots_count }} ditandai
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if(session('success'))
        <p role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </p>
    @endif

    @if($errors->has('coordinates'))
        <p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first('coordinates') }}
        </p>
    @endif

    @if(!$hasLots)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">Langkah 2 belum lengkap, jadi pemetaan belum bisa dimulai.</p>
            @if($lots->isEmpty())
                <p class="mt-1">Tambahkan blok terlebih dahulu, buka setiap blok, lalu masukkan semua kavlingnya. Setelah itu kembali ke halaman ini.</p>
            @elseif($incompleteBlocks->isNotEmpty())
                <p class="mt-1">Tambahkan kavling ke blok berikut terlebih dahulu: {{ $incompleteBlocks->implode(', ') }}. Setelah itu kembali ke halaman ini.</p>
            @else
                <p class="mt-1">Pastikan blok dan seluruh kavling sudah dibuat sebelum mulai menandai area denah.</p>
            @endif
            @if($manageLotsUrl)
                <a href="{{ $manageLotsUrl }}" class="mt-3 inline-flex rounded-lg bg-amber-800 px-3 py-2 font-semibold text-white hover:bg-amber-900">
                    Mulai dari kelola blok &amp; kavling →
                </a>
            @else
                <p class="mt-3">Hubungi admin untuk melengkapi blok dan kavling sebelum pemetaan dilakukan.</p>
            @endif
        </div>
    @else
        <div class="grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">1. Pilih kavling</p>
                <p class="mt-1 text-sm text-slate-700">Pilih nomor kavling dari daftar. Nama blok ditampilkan bersama nomor kavling.</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">2. Tarik area di denah</p>
                <p class="mt-1 text-sm text-slate-700">Tekan dan tarik dari sudut kiri atas sampai sudut kanan bawah kavling. Kotak oranye menunjukkan area yang sedang dipilih.</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">3. Simpan dan ulangi</p>
                <p class="mt-1 text-sm text-slate-700">Tekan Simpan Area Kavling. Pilih nomor berikutnya dan ulangi sampai penghitung menunjukkan semua selesai.</p>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route($coordinateRoute, ['property' => $property, 'lot' => old('lot_id', $lots->first()->id)]) }}"
            data-site-plan-editor
            class="space-y-4"
        >
            @csrf
            @method('PUT')

            <div class="max-w-sm">
                <label for="site-plan-lot" class="mb-1 block text-sm font-medium text-gray-700">Kavling yang ditandai</label>
                <select id="site-plan-lot" name="lot_id" data-site-plan-lot-select class="w-full rounded-lg border-gray-300">
                    @foreach($lots as $lot)
                        <option
                            value="{{ $lot->id }}"
                            data-url="{{ route($coordinateRoute, ['property' => $property, 'lot' => $lot]) }}"
                            data-x="{{ $lot->map_coordinates['x'] ?? '' }}"
                            data-y="{{ $lot->map_coordinates['y'] ?? '' }}"
                            data-width="{{ $lot->map_coordinates['width'] ?? '' }}"
                            data-height="{{ $lot->map_coordinates['height'] ?? '' }}"
                            @selected((string) old('lot_id', $lots->first()->id) === (string) $lot->id)
                        >
                            {{ $lot->block->name }}/{{ $lot->lot_number }}
                        </option>
                    @endforeach
                </select>
            </div>

            <input type="hidden" name="coordinates[x]" data-site-plan-coordinate="x" value="{{ old('coordinates.x') }}">
            <input type="hidden" name="coordinates[y]" data-site-plan-coordinate="y" value="{{ old('coordinates.y') }}">
            <input type="hidden" name="coordinates[width]" data-site-plan-coordinate="width" value="{{ old('coordinates.width') }}">
            <input type="hidden" name="coordinates[height]" data-site-plan-coordinate="height" value="{{ old('coordinates.height') }}">

            <div class="overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-2">
                <div data-site-plan-canvas class="relative w-full cursor-crosshair touch-none select-none">
                    <img
                        src="{{ $property->sitePlanUrl() }}"
                        alt="Denah {{ $property->name }} untuk penandaan kavling"
                        draggable="false"
                        class="pointer-events-none block h-auto w-full"
                    >
                    @foreach($lots as $lot)
                        @if(is_array($lot->map_coordinates))
                            <div
                                aria-hidden="true"
                                class="pointer-events-none absolute border border-blue-500 bg-blue-500/20"
                                style="left: {{ $lot->map_coordinates['x'] }}%; top: {{ $lot->map_coordinates['y'] }}%; width: {{ $lot->map_coordinates['width'] }}%; height: {{ $lot->map_coordinates['height'] }}%;"
                            >
                                <span class="block overflow-hidden text-ellipsis whitespace-nowrap bg-blue-700/80 px-0.5 text-[9px] font-bold text-white">
                                    {{ $lot->block->name }}/{{ $lot->lot_number }}
                                </span>
                            </div>
                        @endif
                    @endforeach
                    <div data-site-plan-draft hidden aria-hidden="true" class="pointer-events-none absolute border-2 border-dashed border-orange-600 bg-orange-500/30"></div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Simpan Area Kavling
                </button>
                <span data-site-plan-draw-status class="text-sm text-gray-500">Tarik pada denah untuk memilih area.</span>
            </div>

            <dialog
                data-site-plan-confirm
                aria-label="Konfirmasi perubahan posisi kavling"
                class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border-0 bg-transparent p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/60"
            >
                <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-slate-900/10">
                    <div class="flex items-start gap-4 border-b border-amber-100 bg-amber-50 px-6 py-5">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xl text-amber-700" aria-hidden="true">!</span>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Area kavling sudah ditandai</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-600">
                                Kavling <strong data-site-plan-confirm-lot class="text-slate-900"></strong> sudah memiliki posisi pada denah. Posisi yang tersimpan akan diganti dengan area yang baru.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col-reverse gap-2 p-5 sm:flex-row sm:justify-end">
                        <button type="button" data-site-plan-confirm-cancel class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Batal
                        </button>
                        <button type="button" data-site-plan-confirm-accept class="rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-700 focus:outline-2 focus:outline-offset-2 focus:outline-amber-600">
                            Ya, lanjut simpan
                        </button>
                    </div>
                </div>
            </dialog>
        </form>
    @endif
</section>
