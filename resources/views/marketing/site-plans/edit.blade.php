<x-layouts::app :title="'Kelola Denah'">
    <div class="space-y-6 p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-blue-700">Kelola denah perumahan</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ $property->name }}</h1>
                <p class="mt-1 text-sm text-gray-600">Unggah gambar denah dan tandai posisi setiap kavling.</p>
            </div>
            <a href="{{ route('marketing.dashboard', ['property_id' => $property->id]) }}#site-plan" class="inline-flex w-fit rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Kembali ke dashboard
            </a>
        </div>

        @if(session('success'))
            <p role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </p>
        @endif

        @if($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold">Denah belum berhasil disimpan:</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section id="site-plan-upload" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">1. Unggah gambar denah</h2>
                <p class="mt-1 text-sm text-gray-600">Format JPG, PNG, atau WebP; maksimal 10 MB. Mengganti gambar akan menghapus posisi kavling lama, sehingga semua posisi perlu ditandai ulang.</p>
            </div>

            @if($property->site_plan_path)
                <img
                    src="{{ Storage::disk('public')->url($property->site_plan_path) }}"
                    alt="Denah {{ $property->name }}"
                    class="max-h-96 rounded-lg border border-gray-200 object-contain"
                >
            @endif

            <form method="POST" action="{{ route('marketing.site-plans.update', $property) }}" enctype="multipart/form-data" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                @method('PUT')
                <div class="w-full max-w-xl">
                    <label for="site_plan" class="mb-2 block text-sm font-medium text-gray-700">
                        {{ $property->site_plan_path ? 'Pilih gambar denah baru' : 'Pilih gambar denah' }}
                    </label>
                    <input
                        type="file"
                        id="site_plan"
                        name="site_plan"
                        accept="image/jpeg,image/png,image/webp"
                        required
                        class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm"
                    >
                </div>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                    Simpan Gambar Denah
                </button>
            </form>
        </section>

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
        @else
            <p class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                Setelah gambar denah disimpan, bagian pemetaan posisi kavling akan tersedia di halaman ini.
            </p>
        @endif
    </div>
</x-layouts::app>
