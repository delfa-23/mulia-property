<x-layouts::app :title="'Progress ' . $lot->lot_number">

    @php
        $rab = $lot->budgets->sum('amount');
        $realisasi = (float) $constructionRealizationTotal;
        $depiasi = $rab - $realisasi;
        $progressFisik = $lot->constructionProgressPercentage();
        $isAdmin = auth()->user()->isAdmin();

        $booking = $lot->bookings->first();
        $customer = $booking?->customer;
    @endphp
    

    <div class="space-y-6">

        {{-- HEADER --}}
        <div>
            <a
                href="{{ route('pembangunan.progress.index') }}"
                class="text-sm font-medium text-gray-500 hover:text-gray-700"
            >
                ← Kembali ke Progress Kavling
            </a>

            <div class="mt-3">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Progress Kavling {{ $lot->lot_number }}
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                    {{ $property->name }} · {{ $block->name }}
                </p>
            </div>
        </div>

        <div
            x-data="{
                modalOpen: false,
                progressId: null,
                storeUrl: @js(route('pembangunan.progress.store', [$property, $block, $lot])),
                updateUrl: @js(route('pembangunan.progress.records.update', [$property, $block, $lot, '__PROGRESS_ID__'])),
                stageId: '',
                stageName: '',
                stageWeight: 0,
                progress: 0,
                realization: 0,
                notes: '',

                formatRupiah(value) {
                    return `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
                },

                weightedProgress() {
                    return Number(this.progress || 0) * Number(this.stageWeight || 0) / 100;
                },

                openEditModal(button) {
                    this.progressId = button.dataset.progressId
                        ? Number(button.dataset.progressId)
                        : null;
                    this.stageId = Number(button.dataset.stageId);
                    this.stageName = button.dataset.stageName;
                    this.stageWeight = Number(button.dataset.stageWeight);
                    this.progress = Number(button.dataset.progress);
                    this.realization = this.formatRupiah(button.dataset.realization);
                    this.notes = button.dataset.notes;
                    this.modalOpen = true;
                },

                formAction() {
                    return this.progressId
                        ? this.updateUrl.replace('__PROGRESS_ID__', this.progressId)
                        : this.storeUrl;
                },

                closeModal() {
                    this.modalOpen = false;
                }
            }"
        >


        {{-- INFORMASI KAVLING --}}
        <div class="grid gap-4 md:grid-cols-2">

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">

                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Informasi Kavling
                </h2>

                <dl class="mt-4 space-y-3 text-sm">

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Perumahan</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">
                            {{ $property->name }}
                        </dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Kavling</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">
                            {{ $block->name }}/{{ $lot->lot_number }}
                        </dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Nama Konsumen</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">
                            {{ $customer?->name ?? '-' }}
                        </dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Harga Rumah</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">
                            Rp {{ number_format($lot->house_price, 0, ',', '.') }}
                        </dd>
                    </div>

                </dl>

            </div>


            {{-- RAB --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">

                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Anggaran Pembangunan
                </h2>

                <dl class="mt-4 space-y-4">

                    <div>
                        <p class="text-sm text-gray-500">
                            RAB
                        </p>

                        <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                            Rp {{ number_format($rab, 0, ',', '.') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">
                            Realisasi Pengeluaran
                        </p>

                        <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                            Rp {{ number_format($realisasi, 0, ',', '.') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">
                            Depiasi
                        </p>

                        <p class="mt-1 text-xl font-bold {{ $depiasi >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            Rp {{ number_format($depiasi, 0, ',', '.') }}
                        </p>
                    </div>

                </dl>

            </div>

        </div>


        {{-- PROGRESS FISIK --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">

            <div class="flex items-center justify-between gap-4">

                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Progress Fisik
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Total progress dihitung dari progress tiap tahap dikalikan bobotnya.
                    </p>
                </div>

                <span class="text-2xl font-bold text-red-700 dark:text-red-300">
                    {{ number_format($progressFisik, 2) }}%
                </span>

            </div>

            <div class="mt-5 h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-zinc-700">

                <div
                    class="h-full rounded-full bg-red-600"
                    style="width: {{ min(100, max(0, $progressFisik)) }}%"
                ></div>

            </div>

        </div>

        


        {{-- TAHAPAN PEMBANGUNAN --}}
        <div class="mt-6 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">

            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <h2 class="text-lg font-semibold">
                    Tahapan Pembangunan
                </h2>

                <p class="mt-1 text-sm text-zinc-500">
                    Isi progress pekerjaan 0–100%. Bobot hasil tiap tahap adalah progress dikalikan bobot tahap.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="bg-green-50 px-6 py-3 text-green-800">Tahapan</th>
                            <th class="bg-green-50 px-6 py-3 text-green-800">Bobot</th>
                            <th class="bg-orange-50 px-6 py-3 text-orange-800">Progress</th>
                            <th class="bg-red-50 px-6 py-3 text-red-800">Bobot Hasil</th>
                            <th class="px-6 py-3">Realisasi</th>
                            <th class="px-6 py-3">Catatan</th>
                            <th class="px-6 py-3">Terakhir Update</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">

                        @foreach($stages as $stage)

                            @php
                                $progressData = $lot->constructionProgresses
                                    ->firstWhere('stage_id', $stage->id);
                                $stageProgress = (float) ($progressData?->progress ?? 0);
                                $weightedProgress = $progressData?->weightedProgressPercentage() ?? 0;
                            @endphp

                            <tr>
                                <td class="bg-green-50/60 px-6 py-4">
                                    <div class="font-medium">
                                        {{ $stage->name }}
                                    </div>

                                    @if($stage->description)
                                        <div class="mt-1 text-xs text-zinc-500">
                                            {{ $stage->description }}
                                        </div>
                                    @endif
                                </td>

                                <td class="bg-green-50/60 px-6 py-4">
                                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800">
                                        {{ number_format((float) $stage->max_progress, 2) }}%
                                    </span>
                                </td>

                                <td class="bg-orange-50/60 px-6 py-4">
                                    <span class="font-semibold text-orange-800">
                                        {{ number_format($stageProgress, 2) }}%
                                    </span>
                                </td>

                                <td class="bg-red-50/60 px-6 py-4 font-semibold text-red-700 dark:text-red-300">
                                    {{ number_format($weightedProgress, 2) }}%
                                </td>

                                <td class="bg-green-50/60 px-6 py-4 whitespace-nowrap">
                                    Rp {{ number_format((float) ($progressData?->realization ?? 0), 0, ',', '.') }}
                                </td>

                                <td class="px-6 py-4 text-sm text-zinc-600">
                                    {{ $progressData?->notes ?: '-' }}
                                </td>

                                <td class="px-6 py-4 text-zinc-500">
                                    @if($progressData)
                                        {{ $progressData->updated_at?->format('d/m/Y H:i') }}
                                    @else
                                        -
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <button
                                        type="button"
                                        @click="openEditModal($el)"
                                        data-progress-id="{{ $progressData?->id }}"
                                        data-stage-id="{{ $stage->id }}"
                                        data-stage-name="{{ $stage->name }}"
                                        data-stage-weight="{{ $stage->max_progress }}"
                                        data-progress="{{ $progressData?->progress ?? 0 }}"
                                        data-realization="{{ $progressData?->realization ?? 0 }}"
                                        data-notes="{{ $progressData?->notes ?? '' }}"
                                        class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700"
                                    >
                                        {{ $progressData ? 'Edit Progress' : 'Tambah Progress' }}
                                    </button>
                                    @if($progressData && $isAdmin)
                                        <form
                                            method="POST"
                                            action="{{ route('pembangunan.progress.records.destroy', [$property, $block, $lot, $progressData]) }}"
                                            class="mt-2"
                                            onsubmit="return confirm('Hapus progress tahap ini? Riwayat perubahan tetap disimpan.')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50">
                                                Hapus
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>

                        @endforeach

                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
    <div class="mb-5">
        <h2 class="text-lg font-semibold text-zinc-900">
            Riwayat Progress
        </h2>

        <p class="mt-1 text-sm text-zinc-500">
            Riwayat perubahan progress pembangunan kavling.
        </p>
    </div>

    @if($progressHistories->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 p-8 text-center">
            <p class="text-sm text-zinc-500">
                Belum ada riwayat perubahan progress.
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200">
                <thead>
                    <tr class="text-left">
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500">
                            Tanggal
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500">
                            Tahapan
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500">
                            Perubahan
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500">
                            Realisasi
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500">
                            Diubah Oleh
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500">
                            Catatan
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-100">
                    @foreach($progressHistories as $item)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-zinc-700">
                                {{ $item->created_at?->format('d/m/Y H:i') }}
                            </td>

                            <td class="px-4 py-4 text-sm font-medium text-zinc-900">
                                {{ $item->stage?->name ?? 'Tahap telah dihapus' }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="font-medium text-zinc-700">
                                    {{ number_format((float) $item->previous_progress, 2) }}%
                                </span>

                                <span class="mx-1 text-zinc-400">
                                    →
                                </span>

                                <span class="font-semibold text-zinc-900">
                                    {{ number_format((float) $item->progress, 2) }}%
                                </span>
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 text-sm">
                                Rp {{ number_format((float) ($item->previous_realization ?? 0), 0, ',', '.') }}
                                →
                                Rp {{ number_format((float) $item->realization, 0, ',', '.') }}
                            </td>

                            <td class="px-4 py-4 text-sm text-zinc-700">
                                {{ $item->updatedBy?->name ?? '-' }}
                            </td>

                            <td class="px-4 py-4 text-sm text-zinc-600">
                                {{ $item->notes ?: '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
<div
    x-show="modalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
>
    <div
        @click.outside="closeModal()"
        class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900"
    >

        <div class="mb-6">
            <h2 class="text-lg font-semibold">
                <span x-text="progressId ? 'Edit Progress' : 'Tambah Progress'"></span>
            </h2>

            <p class="mt-1 text-sm text-zinc-500">
                <span x-text="stageName"></span>
            </p>
        </div>

        <form
            method="POST"
            :action="formAction()"
        >
            @csrf
            <input type="hidden" name="_method" value="PUT" :disabled="!progressId">

            <input
                type="hidden"
                name="stage_id"
                x-model="stageId"
            >

            <div class="space-y-5">

                <div>
                    <label class="mb-2 block text-sm font-medium">
                        Bobot Tahap
                    </label>

                    <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">
                        <span x-text="stageWeight"></span>%
                    </div>
                </div>

                <div>
                    <label
                        for="modal-progress"
                        class="mb-2 block text-sm font-medium"
                    >
                        Progress (%)
                    </label>

                    <input
                        id="modal-progress"
                        type="number"
                        name="progress"
                        x-model="progress"
                        min="0"
                        max="100"
                        step="0.01"
                        required
                        class="w-full rounded-lg border border-orange-300 bg-orange-50 px-4 py-2 dark:border-orange-700 dark:bg-zinc-800"
                    >

                    <p class="mt-1 text-xs text-zinc-500">
                        Masukkan progress pekerjaan aktual dari 0 sampai 100%.
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">
                        Bobot Hasil Tahap
                    </label>

                    <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:bg-red-900/20 dark:text-red-300">
                        <span x-text="weightedProgress().toFixed(2)"></span>%
                    </div>
                    <p class="mt-1 text-xs text-zinc-500">
                        Progress (%) × Bobot (%) ÷ 100.
                    </p>
                </div>

                @if($isAdmin)
                <div>
                    <label
                        for="modal-notes"
                        class="mb-2 block text-sm font-medium"
                    >
                        Catatan
                    </label>

                    <textarea
                        id="modal-notes"
                        name="notes"
                        x-model="notes"
                        rows="4"
                        class="w-full rounded-lg border border-zinc-300 px-4 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                        placeholder="Tambahkan catatan jika diperlukan..."
                    ></textarea>
                </div>

                <div>
                    <label
                        for="modal-realization"
                        class="mb-2 block text-sm font-medium"
                    >
                        Realisasi Pengeluaran (Rp)
                    </label>

                    <input
                        id="modal-realization"
                        type="text"
                        name="realization"
                        inputmode="decimal"
                        x-model="realization"
                        data-currency-input
                        data-currency-min="0"
                        required
                        class="w-full rounded-lg border border-green-300 bg-green-50 px-4 py-2 dark:border-green-700 dark:bg-zinc-800"
                    >
                    <p class="mt-1 text-xs text-zinc-500">
                        Nominal akan dikirim sebagai angka dan disimpan tanpa format.
                    </p>
                </div>
                @endif

            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    @click="closeModal()"
                    class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-50"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    <span x-text="progressId ? 'Simpan Perubahan' : 'Tambah Progress'"></span>
                </button>
            </div>

        </form>
    </div>
</div>

    </div>

</x-layouts::app>