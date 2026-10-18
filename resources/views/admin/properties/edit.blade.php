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

        <section aria-labelledby="site-plan-steps-title" class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Panduan pengisian denah</p>
                    <h2 id="site-plan-steps-title" class="mt-1 text-lg font-bold text-gray-900">Ikuti urutan ini agar setiap kavling bisa dipetakan</h2>
                </div>
                <p class="text-sm text-gray-600">
                    @if(!$hasSitePlan)
                        Mulai dari langkah 1.
                    @elseif(!$hasLots)
                        Denah sudah diunggah. Lanjutkan ke langkah 2.
                    @elseif(!$allLotsMapped)
                        {{ $mappedLotCount }} dari {{ $lots->count() }} kavling sudah ditandai.
                    @else
                        Semua {{ $lots->count() }} kavling sudah ditandai.
                    @endif
                </p>
            </div>

            <ol class="mt-4 grid gap-3 lg:grid-cols-3">
                <li class="rounded-lg border border-white bg-white p-4">
                    <div class="flex items-center gap-2">
                        <span class="flex size-7 items-center justify-center rounded-full text-sm font-bold {{ $hasSitePlan ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-700 text-white' }}">
                            {{ $hasSitePlan ? '✓' : '1' }}
                        </span>
                        <h3 class="font-semibold text-gray-900">Unggah gambar denah</h3>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">
                        Gunakan denah yang jelas dan tidak terpotong. Format JPG, PNG, atau WebP, maksimal 10 MB.
                    </p>
                    <a href="#site-plan-upload" class="mt-3 inline-flex text-sm font-semibold text-blue-700 hover:underline">
                        {{ $hasSitePlan ? 'Lihat / ganti gambar' : 'Unggah gambar di bawah' }} ↓
                    </a>
                </li>

                <li class="rounded-lg border border-white bg-white p-4">
                    <div class="flex items-center gap-2">
                        <span class="flex size-7 items-center justify-center rounded-full text-sm font-bold {{ $hasLots ? 'bg-emerald-100 text-emerald-800' : ($hasSitePlan ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-500') }}">
                            {{ $hasLots ? '✓' : '2' }}
                        </span>
                        <h3 class="font-semibold text-gray-900">Lengkapi blok dan semua kavling</h3>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">
                        Buat semua blok, lalu tambahkan setiap kavling ke blok yang sesuai, termasuk nomor, harga, dan status awal.
                        @if($hasLots)
                            Saat ini ada {{ $blocks->count() }} blok dan {{ $lots->count() }} kavling.
                        @else
                            Selesaikan langkah ini sebelum mulai menandai gambar.
                        @endif
                    </p>
                    <a href="{{ route($routePrefix.'.blocks.index', $property) }}" class="mt-3 inline-flex text-sm font-semibold text-blue-700 hover:underline">
                        Kelola blok &amp; kavling →
                    </a>
                </li>

                <li class="rounded-lg border border-white bg-white p-4">
                    <div class="flex items-center gap-2">
                        <span class="flex size-7 items-center justify-center rounded-full text-sm font-bold {{ $allLotsMapped ? 'bg-emerald-100 text-emerald-800' : ($hasSitePlan && $hasLots ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-500') }}">
                            {{ $allLotsMapped ? '✓' : '3' }}
                        </span>
                        <h3 class="font-semibold text-gray-900">Tandai posisi setiap kavling</h3>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">
                        Pilih nomor kavling, tarik kotak tepat di atas areanya pada denah, lalu simpan. Ulangi sampai semua kavling selesai.
                    </p>
                    @if($hasSitePlan && $hasLots)
                        <a href="#site-plan-editor" class="mt-3 inline-flex text-sm font-semibold text-blue-700 hover:underline">
                            {{ $allLotsMapped ? 'Periksa penandaan denah' : 'Mulai tandai kavling' }} ↓
                        </a>
                    @else
                        <span class="mt-3 inline-flex text-sm text-gray-400">Aktif setelah denah dan kavling tersedia.</span>
                    @endif
                </li>
            </ol>

            <p class="mt-4 rounded-lg border border-blue-100 bg-white/80 px-4 py-3 text-sm text-gray-700">
                <strong>Catatan:</strong> penandaan disimpan satu kavling setiap kali menekan tombol simpan. Posisi yang sudah disimpan akan tetap terlihat saat memilih kavling lain.
                Jika gambar denah diganti, posisi penandaan perlu dibuat ulang karena ukuran dan tata letaknya mungkin berbeda.
            </p>
        </section>

        <div class="max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

            <form
                method="POST"
                action="{{ route($routePrefix.'.update', $property) }}"
                enctype="multipart/form-data"
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

                <div id="site-plan-upload">
                    <label for="site_plan" class="mb-2 block text-sm font-medium text-gray-700">
                        Gambar Denah Perumahan
                    </label>
                    @if($property->site_plan_path)
                        <img
                            src="{{ $property->sitePlanUrl() }}"
                            alt="Denah {{ $property->name }}"
                            class="mb-3 max-h-64 rounded-lg border border-gray-200 object-contain"
                        >
                    @endif
                    <input
                        type="file"
                        id="site_plan"
                        name="site_plan"
                        accept="image/jpeg,image/png,image/webp"
                        class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm"
                    >
                    <p class="mt-1 text-xs text-gray-500">Pilih gambar baru untuk mengganti denah saat ini. JPG, PNG, atau WebP; maksimal 10 MB.</p>
                    @error('site_plan')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
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

        @if($property->site_plan_path)
            @include('admin.properties._site-plan-editor', [
                'property' => $property,
                'blocks' => $blocks,
                'lots' => $lots,
                'mappedLotCount' => $mappedLotCount,
                'mappedLotCountsByBlock' => $mappedLotCountsByBlock,
                'hasLots' => $hasLots,
                'incompleteBlocks' => $incompleteBlocks,
                'coordinateRoute' => $coordinateRoute,
                'manageLotsUrl' => $manageLotsUrl,
            ])
        @endif

    </div>

</x-layouts::app>