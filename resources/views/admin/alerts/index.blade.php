<x-layouts::app :title="__('Alerts / Miss-com')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Alerts / Miss-com</h1>
            <p class="mt-1 text-sm text-zinc-500">
                Admin melihat seluruh alert. Divisi melihat alert yang ditujukan kepadanya dan alert yang dikirimnya.
            </p>
        </div>

        @if(session('success'))
            <div role="status" class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-6 py-3">Urgensi</th>
                            <th class="px-6 py-3">Alert</th>
                            <th class="px-6 py-3">Pengirim</th>
                            <th class="px-6 py-3">Tujuan</th>
                            <th class="px-6 py-3">Kavling</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($alerts as $alert)
                            <tr>
                                <td class="px-6 py-4">{{ ucfirst($alert->severity) }}</td>
                                <td class="px-6 py-4">
                                    <p class="font-medium">{{ $alert->title }}</p>
                                    <p class="text-zinc-500">{{ $alert->message }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    {{ $alert->createdBy?->name ?? '-' }}
                                    <p class="text-xs text-zinc-400">{{ $alert->createdBy?->division?->name ?? '-' }}</p>
                                </td>
                                <td class="px-6 py-4">{{ $alert->recipientDivisions->pluck('name')->join(', ') ?: 'Admin' }}</td>
                                <td class="px-6 py-4">{{ $alert->lot?->lot_number ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $alert->is_resolved ? 'Selesai' : 'Aktif' }}</td>
                                <td class="px-6 py-4">
                                    @if(! $alert->is_resolved && ($isAdmin || $alert->recipientDivisions->contains('id', $divisionId)))
                                        <form method="POST" action="{{ route('alerts.resolve', $alert) }}">
                                            @csrf
                                            <button type="submit" class="font-medium text-blue-700 hover:underline">Tandai selesai</button>
                                        </form>
                                    @elseif($alert->is_resolved)
                                        {{ $alert->resolvedBy?->name ?? 'Selesai' }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-zinc-500">
                                    Belum ada alert untuk ditampilkan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $alerts->links() }}</div>
        </div>
    </div>
</x-layouts::app>
