<x-layouts::app :title="__('Arsip Foto Progress')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('reports.progress.index') }}" class="text-sm text-zinc-500" wire:navigate>Kembali ke laporan</a>
                <h1 class="mt-2 text-2xl font-semibold">Arsip Foto Progress Kavling</h1>
                <p class="mt-1 text-sm text-zinc-500">Foto sebelumnya yang digantikan setelah laporan baru disetujui admin.</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-6 py-3">Foto</th>
                            <th class="px-6 py-3">Perumahan / Kavling</th>
                            <th class="px-6 py-3">Laporan</th>
                            <th class="px-6 py-3">Dibuat oleh</th>
                            <th class="px-6 py-3">Tanggal arsip</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($reports as $report)
                            <tr>
                                <td class="px-6 py-4">
                                    <a href="{{ $report->photoUrl() }}" target="_blank" rel="noopener">
                                        <img src="{{ $report->photoUrl() }}" alt="Foto progress {{ $report->title }}" class="h-20 w-28 rounded-lg object-cover">
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    {{ $report->property?->name ?? '-' }}
                                    <p class="mt-1 text-xs text-zinc-500">{{ $report->lot?->block?->name }}/{{ $report->lot?->lot_number }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-medium">{{ $report->title }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ $report->photo_archived_at?->format('d/m/Y H:i') }}</p>
                                </td>
                                <td class="px-6 py-4">{{ $report->creator?->name ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $report->photo_archived_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-zinc-500">Belum ada foto progress yang diarsipkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $reports->links() }}</div>
        </div>
    </div>
</x-layouts::app>
