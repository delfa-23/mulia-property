<x-layouts::app :title="__('Progress Report')">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Progress Report</h1>
                <p class="mt-1 text-sm text-zinc-500">Laporan progress division dan review TL.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('reports.progress.export') }}" class="rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-semibold dark:border-zinc-600">
                        Unduh Excel
                    </a>
                @endif

                @if(auth()->user()->isStaff() && auth()->user()->hasValidDivisionAssignment())
                    <a href="{{ route('reports.progress.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white" wire:navigate>
                        Buat Laporan
                    </a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-6 py-3">Periode</th>
                            <th class="px-6 py-3">Laporan dan Rincian</th>
                            <th class="px-6 py-3">Pembuat</th>
                            <th class="px-6 py-3">Progress</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($reportGroups as $divisionReports)
                            <tr class="bg-zinc-100 dark:bg-zinc-800">
                                <th colspan="6" class="px-6 py-3 text-left font-semibold">
                                    {{ $divisionReports->first()->division?->name ?? 'Divisi tidak diketahui' }}
                                    <span class="ml-2 text-xs font-normal text-zinc-500 dark:text-zinc-400">
                                        {{ $divisionReports->count() }} laporan
                                    </span>
                                </th>
                            </tr>
                            @foreach($divisionReports as $report)
                            <tr>
                                <td class="px-6 py-4">
                                    {{ $report->period_start->format('d/m/Y') }} - {{ $report->period_end->format('d/m/Y') }}
                                    @if($report->period_end->lt(today()))
                                        <span role="status" class="mt-1 block rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                            Periode laporan {{ $report->title }} telah berakhir — tenggat {{ $report->period_end->format('d/m/Y') }} terlewati.
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-medium">{{ $report->title }}</p>
                                    <p class="mt-1 whitespace-pre-line text-xs text-zinc-600 dark:text-zinc-300">
                                        {{ filled($report->content) ? $report->content : 'Belum ada rincian.' }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">{{ $report->creator?->name ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    {{ $report->progress !== null ? number_format((float) $report->progress, 2).'%' : '-' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium">
                                        {{ ucfirst($report->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if(auth()->user()->isStaff() && in_array($report->status, ['draft', 'revision'], true))
                                        <div class="flex flex-wrap gap-3">
                                            <a href="{{ route('reports.progress.edit', $report) }}" class="text-blue-600" wire:navigate>
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('reports.progress.submit', $report) }}">
                                                @csrf
                                                <button class="text-green-600">Submit ke TL</button>
                                            </form>
                                        </div>
                                    @elseif($canReview && $report->status === 'submitted')
                                        <div class="flex flex-col gap-2">
                                            <form method="POST" action="{{ route('reports.progress.review', $report) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="approved">
                                                <button class="text-green-600">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('reports.progress.review', $report) }}" class="flex items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="action" value="revision">
                                                <input name="notes" required placeholder="Catatan revisi" class="w-40 rounded border px-2 py-1 text-xs">
                                                <button class="text-amber-600">Tolak / Revisi</button>
                                            </form>
                                        </div>
                                    @elseif($report->status === 'draft' && $canReview)
                                        <span class="text-xs text-zinc-500">Menunggu Staff Submit</span>
                                    @else
                                        <span class="text-xs text-zinc-500">Tidak ada tindakan</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-zinc-500">Belum ada laporan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @unless($isAdmin)
                <div class="px-6 py-4">{{ $reports->links() }}</div>
            @endunless
        </div>
    </div>
</x-layouts::app>
