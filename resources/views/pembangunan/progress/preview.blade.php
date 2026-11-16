<x-layouts::app :title="'Preview Progress Kavling'">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Preview Excel Progress Kavling
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                    {{ count($rows) }} data kavling akan diekspor sesuai filter yang dipilih.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ route('pembangunan.progress.index', request()->query()) }}"
                    class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-gray-100 dark:hover:text-gray-800"
                >
                    Kembali
                </a>
                <a
                    href="{{ route('pembangunan.progress.export', request()->query()) }}"
                    class="inline-flex items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 hover:text-white"
                >
                    Ekspor ke Excel
                </a>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr>
                            @foreach ($headings as $heading)
                                <th class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-zinc-400">
                                    {{ $heading }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-100 dark:hover:bg-gray-100">
                                @foreach ($row as $value)
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-zinc-300">
                                        {{ $value }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="{{ count($headings) }}"
                                    class="px-5 py-10 text-center text-sm text-gray-500 dark:text-zinc-400"
                                >
                                    Tidak ada data kavling untuk filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts::app>
