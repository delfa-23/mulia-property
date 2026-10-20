<x-layouts::app :title="__('Prospek Marketing')">
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-2xl font-semibold">Prospek Marketing</h1>
                <p class="mt-1 text-sm text-zinc-500">Catat lead masuk, pantau kategorinya, dan lihat prospek yang sudah mencapai akad.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('marketing.reports.crm') }}" class="rounded-lg border border-zinc-300 px-4 py-2.5 text-center text-sm font-semibold dark:border-zinc-600" wire:navigate>Laporan</a>
                <a href="{{ route('marketing.leads.create') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-center text-sm font-semibold text-white" wire:navigate>Tambah Prospek</a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-950 dark:text-green-200">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-200">{{ session('error') }}</div>
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Total Prospek</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format($totalLeads) }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Kategori Hot</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format($hotLeads) }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Sampai Akad</p>
                <p class="mt-2 text-2xl font-semibold">{{ number_format($akadLeads) }}</p>
            </div>
        </div>

        <form method="GET" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5 dark:border-zinc-700 dark:bg-zinc-900">
            @if($showArchived)
                <input type="hidden" name="archived" value="1">
            @endif
            <input name="search" value="{{ request('search') }}" placeholder="Cari nama, telepon, domisili" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
            <select name="category" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                <option value="">Semua Kategori</option>
                @foreach($categories as $category)
                    <option value="{{ $category }}" @selected(request('category') === $category)>{{ \App\Models\MarketingLead::CATEGORY_LABELS[$category] ?? $category }}</option>
                @endforeach
            </select>
            <select name="source" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                <option value="">Semua Sumber</option>
                @foreach($sources as $source)
                    <option value="{{ $source }}" @selected(request('source') === $source)>{{ $source }}</option>
                @endforeach
            </select>
            <select name="sales_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                <option value="">Semua Sales</option>
                @foreach($salesOptions as $salesOption)
                    <option value="{{ $salesOption->id }}" @selected(request('sales_id') == $salesOption->id)>{{ $salesOption->name }}</option>
                @endforeach
            </select>
            @if($teamMembers->isNotEmpty())
                <select name="assigned_user_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                    <option value="">Semua PIC CRM</option>
                    @foreach($teamMembers as $teamMember)
                        <option value="{{ $teamMember->id }}" @selected(request('assigned_user_id') == $teamMember->id)>{{ $teamMember->name }}</option>
                    @endforeach
                </select>
            @endif
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
                <a href="{{ route('marketing.leads.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm dark:border-zinc-600" wire:navigate>Reset</a>
            </div>
        </form>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('marketing.leads.index', array_merge(request()->except('archived', 'page'), ['archived' => '0'])) }}" class="rounded-lg px-4 py-2 text-sm font-semibold {{ !$showArchived ? 'bg-blue-700 text-white' : 'border border-zinc-300 bg-white text-zinc-700 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-300' }}" wire:navigate>Prospek aktif</a>
            <a href="{{ route('marketing.leads.index', array_merge(request()->except('archived', 'page'), ['archived' => '1'])) }}" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $showArchived ? 'bg-zinc-800 text-white dark:bg-zinc-200 dark:text-zinc-900' : 'border border-zinc-300 bg-white text-zinc-700 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-300' }}" wire:navigate>Arsip prospek</a>
            @if($showArchived)
                <p class="text-sm text-zinc-500">Prospek yang diarsipkan tetap tersimpan di database dan riwayat booking-nya tidak dihapus.</p>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-5 py-3">Tanggal Masuk</th>
                            <th class="px-5 py-3">Prospek</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Sumber</th>
                            <th class="px-5 py-3">Marketing / Sales</th>
                            <th class="px-5 py-3">Progres Terakhir</th>
                            <th class="px-5 py-3">Pencapaian</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($leads as $lead)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4">{{ $lead->date_received->format('d/m/Y') }}</td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('marketing.leads.show', $lead) }}" class="font-medium text-blue-600 dark:text-blue-400" wire:navigate>{{ $lead->name }}</a>
                                    @if($lead->archived_at)
                                        <span class="ml-2 rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">Arsip</span>
                                    @endif
                                    <p class="mt-1 text-xs text-zinc-500">{{ $lead->phone ?? '-' }}{{ $lead->domicile ? ' · '.$lead->domicile : '' }}</p>
                                </td>
                                <td class="px-5 py-4">{{ \App\Models\MarketingLead::CATEGORY_LABELS[$lead->category] ?? $lead->category }}</td>
                                <td class="px-5 py-4">{{ $lead->source }}</td>
                                <td class="px-5 py-4">{{ $lead->assignedUser?->name ?? 'Belum ditugaskan' }}<span class="mt-1 block text-xs text-zinc-500">Sales: {{ $lead->sales?->name ?? '-' }}</span></td>
                                <td class="max-w-xs px-5 py-4 text-zinc-600 dark:text-zinc-300">{{ $lead->last_progress ?: '-' }}</td>
                                <td class="px-5 py-4">
                                    @if($lead->akad_bookings_count > 0)
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">Sampai Akad</span>
                                    @elseif($lead->latestBooking)
                                        <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-800 dark:bg-sky-950 dark:text-sky-200">Booking: {{ ucfirst($lead->latestBooking->status) }}</span>
                                    @else
                                        <span class="text-zinc-500">Belum booking</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('marketing.leads.show', $lead) }}" class="inline-flex items-center rounded-lg border border-blue-200 px-3 py-1.5 font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950" wire:navigate>Detail</a>
                                    @if($lead->archived_at)
                                        @if($teamMembers->isNotEmpty())
                                            <form class="ml-2 inline" method="POST" action="{{ route('marketing.leads.restore', $lead) }}">
                                                @csrf
                                                <button class="text-emerald-700 dark:text-emerald-400">Pulihkan</button>
                                            </form>
                                        @endif
                                    @else
                                        <a href="{{ route('marketing.leads.edit', $lead) }}" class="ml-2 text-blue-600 dark:text-blue-400" wire:navigate>Edit</a>
                                        <form class="ml-3 inline" method="POST" action="{{ route('marketing.leads.destroy', $lead) }}" onsubmit="return confirm('Arsipkan prospek ini? Riwayat tetap tersimpan.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-zinc-600 dark:text-zinc-300">Arsipkan</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-10 text-center text-zinc-500">Belum ada prospek yang dicatat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4">{{ $leads->links() }}</div>
        </div>
    </div>
</x-layouts::app>
