<x-layouts::app :title="'Progress ' . $lot->lot_number">

    @php
        $rab = $lot->budgets->sum('amount');
        $realisasi = $lot->expenseTransactions->sum('amount');
        $depiasi = $rab - $realisasi;
        $progressFisik = (float) ($lot->constructionProgresses->whereNotNull('stage_id')->max('progress') ?? 0);

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
                stageId: '',
                stageName: '',
                minProgress: 0,
                maxProgress: 100,
                progress: 0,
                notes: '',

                openEditModal(id, name, min, max, current, note) {
                    this.stageId = id;
                    this.stageName = name;
                    this.minProgress = min;
                    this.maxProgress = max;
                    this.progress = current;
                    this.notes = note;
                    this.modalOpen = true;
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
                        Progress keseluruhan sementara.
                    </p>
                </div>

                <span class="text-2xl font-bold text-blue-600">
                    {{ number_format($progressFisik, 2) }}%
                </span>

            </div>

            <div class="mt-5 h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-zinc-700">

                <div
                    class="h-full rounded-full bg-blue-600"
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
                    Progress mengikuti range yang telah ditentukan Admin.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-6 py-3">Tahapan</th>
                            <th class="px-6 py-3">Range</th>
                            <th class="px-6 py-3">Progress</th>
                            <th class="px-6 py-3">Terakhir Update</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">

                        @foreach($stages as $stage)

                            @php
                                $progressData = $lot->constructionProgresses
                                    ->firstWhere('stage_id', $stage->id);
                            @endphp

                            <tr>
                                <td class="px-6 py-4">
                                    <div class="font-medium">
                                        {{ $stage->name }}
                                    </div>

                                    @if($stage->description)
                                        <div class="mt-1 text-xs text-zinc-500">
                                            {{ $stage->description }}
                                        </div>
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                                        {{ number_format($stage->min_progress, 0) }}%
                                        -
                                        {{ number_format($stage->max_progress, 0) }}%
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    @if($progressData)
                                        <span class="font-semibold">
                                            {{ number_format($progressData->progress, 2) }}%
                                        </span>
                                    @else
                                        <span class="text-zinc-400">
                                            Belum diisi
                                        </span>
                                    @endif
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
                                        @click="openEditModal(
                                            {{ $stage->id }},
                                            @js($stage->name),
                                            {{ $stage->min_progress }},
                                            {{ $stage->max_progress }},
                                            {{ $progressData?->progress ?? $stage->min_progress }},
                                            @js($progressData?->notes ?? '')
                                        )"
                                        class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700"
                                    >
                                        Edit
                                    </button>
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

    @php
        $history = $lot->constructionProgressHistories
            ->sortByDesc('created_at');
    @endphp

    @if($history->isEmpty())
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
                            Diubah Oleh
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold uppercase text-zinc-500">
                            Catatan
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-100">
                    @foreach($history as $item)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-zinc-700">
                                {{ $item->created_at?->format('d/m/Y H:i') }}
                            </td>

                            <td class="px-4 py-4 text-sm font-medium text-zinc-900">
                                {{ $item->constructionProgress?->stage?->name ?? '-' }}
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
                Edit Progress
            </h2>

            <p class="mt-1 text-sm text-zinc-500">
                <span x-text="stageName"></span>
            </p>
        </div>

        <form
            method="POST"
            action="{{ route('pembangunan.progress.update', [$property, $block, $lot]) }}"
        >
            @csrf
            @method('PUT')

            <input
                type="hidden"
                name="stage_id"
                x-model="stageId"
            >

            <div class="space-y-5">

                <div>
                    <label class="mb-2 block text-sm font-medium">
                        Range Progress
                    </label>

                    <div class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-700">
                        <span x-text="minProgress"></span>%
                        -
                        <span x-text="maxProgress"></span>%
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
                        :min="minProgress"
                        :max="maxProgress"
                        step="0.01"
                        required
                        class="w-full rounded-lg border border-zinc-300 px-4 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                    >

                    <p class="mt-1 text-xs text-zinc-500">
                        Nilai harus berada dalam range yang ditentukan Admin.
                    </p>
                </div>

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
                    Simpan Progress
                </button>
            </div>

        </form>
    </div>
</div>

    </div>

</x-layouts::app>