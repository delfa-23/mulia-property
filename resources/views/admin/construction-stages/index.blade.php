<x-layouts::app :title="'Tahapan Pembangunan'">

    <div class="space-y-6">

        <div class="flex items-center justify-between gap-4">

            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Tahapan Pembangunan
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                    Kelola tahapan pembangunan yang digunakan pada progress kavling.
                </p>
            </div>

            <a
                href="{{ route('admin.construction-stages.create') }}"
                class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            >
                + Tambah Tahapan
            </a>

        </div>


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


        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">

            <div class="overflow-x-auto">

                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">

                    <thead class="bg-gray-50 dark:bg-zinc-800">

                        <tr>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Urutan
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Tahapan
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Range Persentase
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Deskripsi
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Status
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Aksi
                            </th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">

                        @forelse($stages as $stage)

                            <tr>

                                <td class="px-5 py-4 text-sm font-medium">
                                    {{ $stage->order }}
                                </td>

                                <td class="px-5 py-4 text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $stage->name }}
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-600 dark:text-zinc-400">
                                    {{ number_format((float) $stage->min_progress, 0) }}% - {{ number_format((float) $stage->max_progress, 0) }}%
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-600 dark:text-zinc-400">
                                    {{ $stage->description ?? '-' }}
                                </td>

                                <td class="px-5 py-4 text-sm">

                                    @if($stage->is_active)
                                        <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">
                                            Aktif
                                        </span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                                            Nonaktif
                                        </span>
                                    @endif

                                </td>

                                <td class="px-5 py-4">

                                    <div class="flex items-center gap-2">

                                        <a
                                            href="{{ route('admin.construction-stages.edit', $stage) }}"
                                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="{{ route('admin.construction-stages.destroy', $stage) }}"
                                            onsubmit="return confirm('Hapus tahapan ini?')"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                            >
                                                Hapus
                                            </button>
                                        </form>

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="6"
                                    class="px-5 py-10 text-center text-sm text-gray-500"
                                >
                                    Belum ada tahapan pembangunan.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</x-layouts::app>