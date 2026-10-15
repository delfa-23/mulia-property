<x-layouts::app :title="__('Follow-up Marketing')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h1 class="text-2xl font-semibold">Tugas Follow-up</h1><p class="mt-1 text-sm text-zinc-500">Pantau tugas marketing, tenggat, dan status penyelesaian.</p></div>
            @if($teamMembers->isNotEmpty())
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="bucket" value="{{ $bucket }}">
                    <select name="assigned_user_id" class="rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="">Semua Marketing</option>@foreach($teamMembers as $teamMember)<option value="{{ $teamMember->id }}" @selected(request('assigned_user_id') == $teamMember->id)>{{ $teamMember->name }}</option>@endforeach</select>
                    <button class="rounded-lg border border-zinc-300 px-4 py-2 text-sm dark:border-zinc-600">Filter</button>
                </form>
            @endif
        </div>
        <nav class="flex flex-wrap gap-2">
            @foreach(['today' => 'Hari ini', 'upcoming' => 'Mendatang', 'overdue' => 'Terlambat', 'completed' => 'Selesai'] as $key => $label)
                <a href="{{ route('marketing.follow-ups.index', array_filter(['bucket' => $key, 'assigned_user_id' => request('assigned_user_id')])) }}" class="rounded-lg px-4 py-2 text-sm font-medium {{ $bucket === $key ? 'bg-blue-700 text-white' : 'border border-zinc-300 dark:border-zinc-600' }}" wire:navigate>{{ $label }}</a>
            @endforeach
        </nav>
        @if(session('success'))<div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-950 dark:text-green-200">{{ session('success') }}</div>@endif
        <div class="space-y-3">
            @forelse($followUps as $followUp)
                @php($isOverdue = in_array($followUp->status, ['pending', 'in_progress'], true) && $followUp->due_at->isPast())
                <article class="rounded-xl border bg-white p-5 dark:bg-zinc-900 {{ $isOverdue ? 'border-red-300 dark:border-red-900' : 'border-zinc-200 dark:border-zinc-700' }}">
                    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2"><h2 class="font-semibold">{{ $followUp->title }}</h2>@if($isOverdue)<span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800 dark:bg-red-950 dark:text-red-200">Terlambat</span>@endif</div>
                            <a href="{{ route('marketing.leads.show', $followUp->lead) }}" class="text-sm font-medium text-blue-700 dark:text-blue-300" wire:navigate>{{ $followUp->lead->name }} · {{ $followUp->lead->phone ?? 'Tanpa nomor' }}</a>
                            <p class="text-sm text-zinc-500">PIC: {{ $followUp->assignedUser?->name ?? 'Belum ditugaskan' }} · Jatuh tempo {{ $followUp->due_at->format('d/m/Y H:i') }} · Prioritas {{ ['low' => 'rendah', 'medium' => 'sedang', 'high' => 'tinggi'][$followUp->priority] ?? $followUp->priority }}</p>
                            @if($followUp->description)<p class="pt-1 text-sm">{{ $followUp->description }}</p>@endif
                            @if($followUp->result_notes)<p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $followUp->result_notes }}</p>@endif
                        </div>
                        <form method="POST" action="{{ route('marketing.leads.follow-ups.update', [$followUp->lead, $followUp]) }}" class="flex flex-wrap gap-2">
                            @csrf @method('PUT')
                            <select name="status" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"><option value="pending" @selected($followUp->status === 'pending')>Belum dikerjakan</option><option value="in_progress" @selected($followUp->status === 'in_progress')>Sedang dikerjakan</option><option value="completed" @selected($followUp->status === 'completed')>Selesai</option><option value="cancelled" @selected($followUp->status === 'cancelled')>Dibatalkan</option></select>
                            <input name="result_notes" value="{{ $followUp->result_notes }}" placeholder="Catatan hasil" class="min-w-36 rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                            <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-medium text-white">Simpan</button>
                        </form>
                    </div>
                    @if($followUp->histories->isNotEmpty())
                        <details class="mt-4 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-700"><summary class="cursor-pointer text-zinc-500">Riwayat perubahan status</summary><ul class="mt-2 space-y-1">@foreach($followUp->histories as $history)<li>{{ $history->from_status ? str_replace('_', ' ', $history->from_status).' → ' : '' }}{{ str_replace('_', ' ', $history->to_status) }} · {{ $history->changed_at->format('d/m/Y H:i') }} · {{ $history->changedBy?->name ?? 'Pengguna tidak tersedia' }} @if($history->notes) — {{ $history->notes }}@endif</li>@endforeach</ul></details>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center text-sm text-zinc-500 dark:border-zinc-700">Tidak ada tugas untuk tampilan ini.</div>
            @endforelse
        </div>
        <div>{{ $followUps->links() }}</div>
    </div>
</x-layouts::app>
