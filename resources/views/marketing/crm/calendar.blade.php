<x-layouts::app :title="__('Kalender CRM')">
    <div class="space-y-5">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Marketing CRM</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">Kalender Aktivitas</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Pantau jadwal survei dan tindak lanjut prospek dalam satu tampilan.</p>
            </div>
            <a href="{{ route('marketing.reports.crm') }}" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800" wire:navigate>Lihat laporan CRM</a>
        </div>

        <form method="GET" class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @if($teamMembers->isNotEmpty())
                    <div><label for="calendar-marketing" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Penanggung jawab</label><select id="calendar-marketing" name="assigned_user_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-600 dark:bg-zinc-800"><option value="">Semua Marketing</option>@foreach($teamMembers as $teamMember)<option value="{{ $teamMember->id }}" @selected(($filters['assigned_user_id'] ?? '') == $teamMember->id)>{{ $teamMember->name }}</option>@endforeach</select></div>
                @endif
                <div><label for="calendar-type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Jenis aktivitas</label><select id="calendar-type" name="type" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-600 dark:bg-zinc-800"><option value="">Semua aktivitas</option><option value="survey" @selected(($filters['type'] ?? '') === 'survey')>Survei</option><option value="follow_up" @selected(($filters['type'] ?? '') === 'follow_up')>Follow-up</option></select></div>
                <div><label for="calendar-status" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Status aktivitas</label><select id="calendar-status" name="status" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-600 dark:bg-zinc-800"><option value="">Semua status</option>@foreach(['scheduled' => 'Dijadwalkan', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', 'no_show' => 'Tidak hadir', 'pending' => 'Belum dikerjakan', 'in_progress' => 'Sedang dikerjakan'] as $status => $label)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>@endforeach</select></div>
                <div><label for="calendar-month" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Pilih bulan</label><input id="calendar-month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-600 dark:bg-zinc-800"></div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <button class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">Terapkan filter</button>
                <a href="{{ route('marketing.calendar') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" wire:navigate>Reset</a>
                <span class="ml-auto text-xs text-zinc-500">{{ $activities->count() }} aktivitas ditemukan</span>
            </div>
        </form>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-4 py-4 sm:px-5 dark:border-zinc-700">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Tampilan bulanan</p>
                    <h2 class="mt-0.5 text-xl font-semibold capitalize">{{ $month->translatedFormat('F Y') }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('marketing.calendar', array_merge(request()->query->all(), ['month' => now()->format('Y-m')])) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium transition hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800" wire:navigate>Bulan ini</a>
                    <a aria-label="Bulan sebelumnya" href="{{ route('marketing.calendar', array_merge(request()->query->all(), ['month' => $month->copy()->subMonth()->format('Y-m')])) }}" class="flex size-9 items-center justify-center rounded-lg border border-zinc-300 text-lg transition hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800" wire:navigate>‹</a>
                    <a aria-label="Bulan berikutnya" href="{{ route('marketing.calendar', array_merge(request()->query->all(), ['month' => $month->copy()->addMonth()->format('Y-m')])) }}" class="flex size-9 items-center justify-center rounded-lg border border-zinc-300 text-lg transition hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800" wire:navigate>›</a>
                </div>
            </div>
            <div class="grid grid-cols-7 border-b border-zinc-200 bg-zinc-50/80 text-center text-[10px] font-semibold uppercase tracking-wide text-zinc-500 sm:text-xs dark:border-zinc-700 dark:bg-zinc-800/80">
                @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday)<div class="px-1 py-3 sm:py-3.5">{{ $weekday }}</div>@endforeach
            </div>
            <div class="grid grid-cols-7 bg-zinc-200 gap-px dark:bg-zinc-700">
                @foreach($calendarDays as $calendarDay)
                    <div class="min-h-24 bg-white p-1.5 sm:min-h-36 sm:p-2.5 dark:bg-zinc-900 {{ !$calendarDay['inMonth'] ? 'bg-zinc-50! text-zinc-400 dark:bg-zinc-950!' : '' }}">
                        <div class="mb-1.5 flex items-center justify-between sm:mb-2">
                            <span class="flex size-6 items-center justify-center rounded-full text-xs font-semibold {{ $calendarDay['date']->isToday() ? 'bg-blue-700 text-white' : ($calendarDay['inMonth'] ? 'text-zinc-700 dark:text-zinc-200' : 'text-zinc-400') }}">{{ $calendarDay['date']->format('j') }}</span>
                            @if($calendarDay['items']->isNotEmpty())
                                <span class="text-[10px] font-medium text-zinc-400 sm:hidden">{{ $calendarDay['items']->count() }}</span>
                                <span class="hidden rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-500 sm:inline dark:bg-zinc-800 dark:text-zinc-400">{{ $calendarDay['items']->count() }} aktivitas</span>
                            @endif
                        </div>
                        <div class="space-y-1">
                            @foreach($calendarDay['items']->take(3) as $item)
                                <a href="{{ $item['url'] }}" title="{{ $item['title'] }} — {{ $item['lead'] }} — {{ $item['marketing'] }} — {{ $item['status'] }}" class="group block rounded-md border-l-2 px-1.5 py-1.5 text-[10px] leading-tight transition hover:brightness-95 sm:px-2 sm:py-2 sm:text-xs {{ $item['type'] === 'survey' ? 'border-purple-500 bg-purple-50 text-purple-950 dark:bg-purple-950/50 dark:text-purple-100' : 'border-blue-500 bg-blue-50 text-blue-950 dark:bg-blue-950/50 dark:text-blue-100' }}" wire:navigate>
                                    <span class="block truncate font-semibold">{{ $item['date']->format('H:i') }} · {{ $item['type'] === 'survey' ? 'Survei' : 'Follow-up' }}</span>
                                    <span class="mt-0.5 hidden truncate text-[11px] opacity-80 sm:block">{{ $item['lead'] }}</span>
                                </a>
                            @endforeach
                            @if($calendarDay['items']->count() > 3)
                                <p class="px-1 text-[10px] font-medium text-zinc-500">+{{ $calendarDay['items']->count() - 3 }} lainnya</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-xs text-zinc-600 shadow-sm sm:text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
            <span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-purple-500"></span>Survei</span>
            <span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-blue-500"></span>Follow-up</span>
            <span class="text-zinc-500">Klik aktivitas untuk membuka detail prospek.</span>
        </div>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 p-5 dark:border-zinc-700"><h2 class="text-lg font-semibold">Agenda bulan ini</h2><p class="mt-1 text-sm text-zinc-500">Daftar aktivitas lengkap, termasuk yang tidak muat pada kotak kalender.</p></div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800"><tr><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Aktivitas</th><th class="px-4 py-3">Prospek</th><th class="px-4 py-3">Marketing</th><th class="px-4 py-3">Status</th></tr></thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($activities as $activity)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3">{{ $activity['date']->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3"><a href="{{ $activity['url'] }}" class="font-medium text-blue-700 dark:text-blue-300" wire:navigate>{{ $activity['title'] }}</a><span class="mt-1 block text-xs text-zinc-500">{{ $activity['type'] === 'survey' ? 'Survei' : 'Follow-up' }}</span></td>
                                <td class="px-4 py-3">{{ $activity['lead'] }}</td>
                                <td class="px-4 py-3">{{ $activity['marketing'] }}</td>
                                <td class="px-4 py-3">{{ ['scheduled' => 'Dijadwalkan', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', 'no_show' => 'Tidak hadir', 'pending' => 'Belum dikerjakan', 'in_progress' => 'Sedang dikerjakan'][$activity['status']] ?? $activity['status'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">Tidak ada aktivitas pada bulan ini dengan filter tersebut.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
