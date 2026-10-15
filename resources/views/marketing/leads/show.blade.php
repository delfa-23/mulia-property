<x-layouts::app :title="$lead->name">
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div class="space-y-2">
                <a href="{{ route('marketing.leads.index') }}" class="text-sm text-zinc-500" wire:navigate>Kembali ke prospek</a>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ $lead->name }}</h1>
                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-950 dark:text-blue-200">{{ \App\Models\MarketingLead::CATEGORY_LABELS[$lead->category] ?? $lead->category }}</span>
                    @if($lead->isArchived())
                        <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">Arsip</span>
                    @endif
                </div>
                <p class="text-sm text-zinc-500">{{ $lead->phone ?? 'Nomor WhatsApp belum dicatat' }} · {{ $lead->domicile ?? 'Domisili belum dicatat' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if(!$lead->isArchived() && $canManageLead)
                    <a href="{{ route('marketing.leads.edit', $lead) }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium dark:border-zinc-600" wire:navigate>Edit prospek</a>
                    @if($lead->category === 'Hot')
                        <a href="{{ route('marketing.bookings.create', ['lead_id' => $lead->id]) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white" wire:navigate>Buat Booking</a>
                    @endif
                @endif
                @if($canManageTeam && $lead->isArchived())
                    <form method="POST" action="{{ route('marketing.leads.restore', $lead) }}">
                        @csrf
                        <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white">Pulihkan prospek</button>
                    </form>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-950 dark:text-green-200">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-200">
                <p class="font-semibold">Periksa kembali data yang dimasukkan:</p>
                <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500">Tanggal masuk</p><p class="mt-1 font-semibold">{{ $lead->date_received->format('d/m/Y') }}</p></div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500">PIC CRM</p><p class="mt-1 font-semibold">{{ $lead->assignedUser?->name ?? 'Belum ditugaskan' }}</p></div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500">Sumber</p><p class="mt-1 font-semibold">{{ $lead->source }}</p></div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">Capaian transaksi</p>
                @php($akadBooking = $lead->bookings->first(fn ($booking) => in_array($booking->status, ['akad', 'finish'], true)))
                <p class="mt-1 font-semibold">{{ $akadBooking ? 'Sampai akad' : ($lead->bookings->isNotEmpty() ? 'Sudah booking' : 'Belum booking') }}</p>
            </div>
        </div>

        @if($lead->last_progress)
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="font-semibold">Progres terakhir</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-300">{{ $lead->last_progress }}</p>
            </div>
        @endif

        @if(!$lead->isArchived() && $canManageLead)
            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h2 class="text-lg font-semibold">Catat panggilan</h2>
                    <form method="POST" action="{{ route('marketing.leads.calls.store', $lead) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div><label for="called-at" class="mb-1 block text-sm font-medium">Tanggal dan waktu</label><input id="called-at" type="datetime-local" name="called_at" value="{{ old('called_at', now()->format('Y-m-d\TH:i')) }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">@error('called_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                        <div><label for="call-outcome" class="mb-1 block text-sm font-medium">Hasil panggilan</label><select id="call-outcome" name="outcome" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="answered">Terjawab</option><option value="unanswered">Tidak terjawab</option><option value="inactive_number">Nomor tidak aktif</option><option value="call_back">Minta dihubungi kembali</option></select></div>
                        <div><label for="call-duration" class="mb-1 block text-sm font-medium">Durasi (menit, opsional)</label><input id="call-duration" type="number" min="0" max="1440" name="duration_minutes" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></div>
                        <div><label for="call-next" class="mb-1 block text-sm font-medium">Rencana tindak lanjut</label><input id="call-next" type="datetime-local" name="next_follow_up_at" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></div>
                        <div class="sm:col-span-2"><label for="call-notes" class="mb-1 block text-sm font-medium">Catatan komunikasi</label><textarea id="call-notes" name="notes" rows="2" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></textarea></div>
                        <div class="sm:col-span-2"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Simpan log panggilan</button></div>
                    </form>
                </section>

                <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h2 class="text-lg font-semibold">Jadwalkan survei</h2>
                    <form method="POST" action="{{ route('marketing.leads.surveys.store', $lead) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div class="sm:col-span-2"><label for="survey-property" class="mb-1 block text-sm font-medium">Perumahan</label><select id="survey-property" name="property_id" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="">Pilih perumahan</option>@foreach($properties as $property)<option value="{{ $property->id }}">{{ $property->name }}</option>@endforeach</select></div>
                        @if($canManageTeam)
                            <div class="sm:col-span-2"><label for="survey-owner" class="mb-1 block text-sm font-medium">Marketing penanggung jawab</label><select id="survey-owner" name="assigned_user_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="">Belum ditugaskan</option>@foreach($teamMembers as $teamMember)<option value="{{ $teamMember->id }}">{{ $teamMember->name }}</option>@endforeach</select></div>
                        @endif
                        <div><label for="survey-scheduled" class="mb-1 block text-sm font-medium">Tanggal dan waktu</label><input id="survey-scheduled" type="datetime-local" name="scheduled_at" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></div>
                        <div><label for="survey-status" class="mb-1 block text-sm font-medium">Status</label><select id="survey-status" name="status" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="scheduled">Dijadwalkan</option><option value="completed">Selesai</option><option value="cancelled">Dibatalkan</option><option value="no_show">Tidak hadir</option></select></div>
                        <div class="sm:col-span-2"><label for="survey-notes" class="mb-1 block text-sm font-medium">Hasil / catatan calon pembeli</label><textarea id="survey-notes" name="result_notes" rows="2" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></textarea></div>
                        <div><label for="survey-next" class="mb-1 block text-sm font-medium">Tindak lanjut berikutnya</label><input id="survey-next" type="datetime-local" name="next_follow_up_at" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></div>
                        <div class="flex items-end"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Simpan survei</button></div>
                    </form>
                </section>
            </div>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">Buat tugas follow-up</h2>
                <form method="POST" action="{{ route('marketing.leads.follow-ups.store', $lead) }}" class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @csrf
                    <div class="xl:col-span-2"><label for="follow-up-title" class="mb-1 block text-sm font-medium">Judul tugas</label><input id="follow-up-title" name="title" required maxlength="255" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></div>
                    @if($canManageTeam)
                        <div><label for="follow-up-owner" class="mb-1 block text-sm font-medium">Marketing penanggung jawab</label><select id="follow-up-owner" name="assigned_user_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="">Belum ditugaskan</option>@foreach($teamMembers as $teamMember)<option value="{{ $teamMember->id }}">{{ $teamMember->name }}</option>@endforeach</select></div>
                    @endif
                    <div><label for="follow-up-due" class="mb-1 block text-sm font-medium">Jatuh tempo</label><input id="follow-up-due" type="datetime-local" name="due_at" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></div>
                    <div><label for="follow-up-priority" class="mb-1 block text-sm font-medium">Prioritas</label><select id="follow-up-priority" name="priority" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="low">Rendah</option><option value="medium" selected>Sedang</option><option value="high">Tinggi</option></select></div>
                    <div class="sm:col-span-2 xl:col-span-3"><label for="follow-up-description" class="mb-1 block text-sm font-medium">Deskripsi</label><textarea id="follow-up-description" name="description" rows="2" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></textarea></div>
                    <div class="flex items-end"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Buat follow-up</button></div>
                </form>
            </section>
        @endif

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">Riwayat panggilan</h2>
                <div class="mt-4 space-y-3">
                    @forelse($calls as $call)
                        <article id="survey-{{ $survey->id }}" class="scroll-mt-24 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                            <div class="flex flex-wrap justify-between gap-2"><p class="font-medium">{{ ['answered' => 'Terjawab', 'unanswered' => 'Tidak terjawab', 'inactive_number' => 'Nomor tidak aktif', 'call_back' => 'Minta dihubungi kembali'][$call->outcome] ?? $call->outcome }}</p><time class="text-sm text-zinc-500">{{ $call->called_at->format('d/m/Y H:i') }}</time></div>
                            <p class="mt-1 text-sm text-zinc-500">
                                Oleh {{ $call->performer?->name ?? 'Pengguna tidak tersedia' }}
                                @if($call->duration_minutes !== null)
                                    · {{ $call->duration_minutes }} menit
                                @endif
                            </p>
                            @if($call->notes)<p class="mt-2 whitespace-pre-line text-sm">{{ $call->notes }}</p>@endif
                            @if($call->next_follow_up_at)<p class="mt-2 text-sm text-blue-700 dark:text-blue-300">Tindak lanjut: {{ $call->next_follow_up_at->format('d/m/Y H:i') }}</p>@endif
                        </article>
                    @empty
                        <p class="text-sm text-zinc-500">Belum ada log panggilan.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">Riwayat survei</h2>
                <div class="mt-4 space-y-3">
                    @forelse($surveys as $survey)
                        <article class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                            <div class="flex flex-wrap justify-between gap-2"><p class="font-medium">{{ $survey->property?->name ?? 'Perumahan tidak tersedia' }}</p><time class="text-sm text-zinc-500">{{ $survey->scheduled_at->format('d/m/Y H:i') }}</time></div>
                            <p class="mt-1 text-sm text-zinc-500">PIC: {{ $survey->assignedUser?->name ?? 'Belum ditugaskan' }} · Status: {{ ['scheduled' => 'Dijadwalkan', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', 'no_show' => 'Tidak hadir'][$survey->status] ?? $survey->status }}</p>
                            @if($survey->result_notes)<p class="mt-2 whitespace-pre-line text-sm">{{ $survey->result_notes }}</p>@endif
                            @if($survey->next_follow_up_at)<p class="mt-2 text-sm text-blue-700 dark:text-blue-300">Tindak lanjut: {{ $survey->next_follow_up_at->format('d/m/Y H:i') }}</p>@endif
                            @if(!$lead->isArchived() && ($canManageTeam || $survey->assigned_user_id === null || (int) $survey->assigned_user_id === (int) $currentUserId))
                                <form method="POST" action="{{ route('marketing.leads.surveys.update', [$lead, $survey]) }}" class="mt-3 flex flex-wrap gap-2">
                                    @csrf @method('PUT')
                                    <select name="status" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"><option value="scheduled" @selected($survey->status === 'scheduled')>Dijadwalkan</option><option value="completed" @selected($survey->status === 'completed')>Selesai</option><option value="cancelled" @selected($survey->status === 'cancelled')>Dibatalkan</option><option value="no_show" @selected($survey->status === 'no_show')>Tidak hadir</option></select>
                                    <input name="result_notes" value="{{ $survey->result_notes }}" placeholder="Catatan hasil" class="min-w-40 flex-1 rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900">
                                    <button class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600">Perbarui</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p class="text-sm text-zinc-500">Belum ada survei.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Tugas follow-up</h2>
                <a href="{{ route('marketing.follow-ups.index') }}" class="text-sm font-medium text-blue-700 dark:text-blue-300" wire:navigate>Lihat semua tugas</a>
            </div>
            <div class="mt-4 space-y-3">
                @forelse($followUps as $followUp)
                    <article id="follow-up-{{ $followUp->id }}" class="scroll-mt-24 rounded-lg border p-4 {{ in_array($followUp->status, ['pending', 'in_progress'], true) && $followUp->due_at->isPast() ? 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/30' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <div class="flex flex-wrap justify-between gap-2"><div><p class="font-medium">{{ $followUp->title }}</p><p class="mt-1 text-sm text-zinc-500">{{ $followUp->assignedUser?->name ?? 'Belum ditugaskan' }} · Prioritas {{ ['low' => 'rendah', 'medium' => 'sedang', 'high' => 'tinggi'][$followUp->priority] ?? $followUp->priority }}</p></div><time class="text-sm {{ in_array($followUp->status, ['pending', 'in_progress'], true) && $followUp->due_at->isPast() ? 'font-semibold text-red-700 dark:text-red-300' : 'text-zinc-500' }}">{{ $followUp->due_at->format('d/m/Y H:i') }}</time></div>
                        @if($followUp->description)<p class="mt-2 text-sm">{{ $followUp->description }}</p>@endif
                        @if($followUp->result_notes)<p class="mt-2 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-300">{{ $followUp->result_notes }}</p>@endif
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium dark:bg-zinc-800">{{ ['pending' => 'Belum dikerjakan', 'in_progress' => 'Sedang dikerjakan', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$followUp->status] ?? $followUp->status }}</span>
                            @if(!$lead->isArchived() && ($canManageTeam || $followUp->assigned_user_id === null || (int) $followUp->assigned_user_id === (int) $currentUserId))
                                <form method="POST" action="{{ route('marketing.leads.follow-ups.update', [$lead, $followUp]) }}" class="flex flex-wrap gap-2">
                                    @csrf @method('PUT')
                                    <select name="status" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"><option value="pending" @selected($followUp->status === 'pending')>Belum dikerjakan</option><option value="in_progress" @selected($followUp->status === 'in_progress')>Sedang dikerjakan</option><option value="completed" @selected($followUp->status === 'completed')>Selesai</option><option value="cancelled" @selected($followUp->status === 'cancelled')>Dibatalkan</option></select>
                                    <input name="result_notes" value="{{ $followUp->result_notes }}" placeholder="Catatan hasil" class="min-w-40 rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                                    <button class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600">Simpan</button>
                                </form>
                            @endif
                        </div>
                        @if($followUp->histories->isNotEmpty())
                            <details class="mt-3 text-sm"><summary class="cursor-pointer text-zinc-500">Riwayat status ({{ $followUp->histories->count() }})</summary><ol class="mt-2 space-y-2 border-l border-zinc-300 pl-4 dark:border-zinc-600">@foreach($followUp->histories as $history)<li><span class="font-medium">{{ $history->from_status ? str_replace('_', ' ', $history->from_status).' → ' : '' }}{{ str_replace('_', ' ', $history->to_status) }}</span><span class="text-zinc-500"> · {{ $history->changed_at->format('d/m/Y H:i') }} oleh {{ $history->changedBy?->name ?? 'Pengguna tidak tersedia' }}</span>@if($history->notes)<p class="text-zinc-600 dark:text-zinc-300">{{ $history->notes }}</p>@endif</li>@endforeach</ol></details>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-zinc-500">Belum ada tugas follow-up.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold">Booking terkait</h2>
            <div class="mt-3 space-y-2">
                @forelse($lead->bookings as $booking)
                    <a href="{{ route('marketing.bookings.show', $booking) }}" class="flex flex-wrap justify-between gap-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800" wire:navigate><span>{{ $booking->lot?->block?->property?->name ?? 'Properti' }} · {{ $booking->lot?->block?->name ?? '' }} {{ $booking->lot?->number ?? '' }} · {{ $booking->customer?->name ?? 'Konsumen' }}</span><span class="font-semibold">{{ ucfirst($booking->status) }}</span></a>
                @empty
                    <p class="text-sm text-zinc-500">Belum ada booking terkait prospek ini.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts::app>
