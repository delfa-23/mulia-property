<x-layouts::app :title="__('Kelola Berkas Booking')">
    <div class="mx-auto max-w-5xl space-y-6 text-zinc-900">
        <div><a href="{{ route('pemberkasan.bookings.index') }}" class="text-sm text-zinc-500" wire:navigate>Kembali</a><h1 class="mt-3 text-2xl font-semibold">{{ $booking->customer->name }} · {{ $booking->lot->lot_number }}</h1><p class="mt-1 text-sm text-zinc-500">{{ $booking->lot->block->property->name }} / {{ $booking->lot->block->name }} · Sales: {{ $booking->sales?->name ?? '-' }}</p></div>
        @if(session('success'))<div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="rounded-xl border border-zinc-200 bg-white p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-semibold">Status Google Drive</h2>
                <form method="POST" action="{{ route('pemberkasan.bookings.documents.sync', $booking) }}">
                    @csrf
                    <button class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Sinkronkan dokumen lama</button>
                </form>
            </div>
            <div class="mt-4 divide-y divide-zinc-200">
                @forelse($booking->pemberkasanDocuments as $document)
                    <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-medium">{{ $document->original_name }}</p>
                            <p class="mt-1 text-sm text-zinc-600">{{ $document->document_type }}</p>
                            @if($document->status === 'uploaded')
                                <p class="mt-2 text-sm font-medium text-green-700">Tersimpan di Google Drive</p>
                                @if($document->uploaded_at)
                                    <p class="mt-1 text-xs text-zinc-500">Diunggah {{ $document->uploaded_at->format('d M Y H:i') }}</p>
                                @endif
                            @elseif($document->status === 'failed')
                                <p class="mt-2 text-sm font-medium text-red-700">Gagal upload ke Google Drive</p>
                                @if($document->upload_error)
                                    <p class="mt-1 text-sm text-red-600">{{ $document->upload_error }}</p>
                                @endif
                            @else
                                <p class="mt-2 text-sm font-medium text-amber-700">
                                    {{ $document->status === 'uploading' ? 'Sedang upload ke Google Drive' : 'Menunggu upload Google Drive' }}
                                </p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            @if($document->drive_url && $document->status === 'uploaded')
                                <a href="{{ $document->drive_url }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-blue-600 hover:underline">Tampilkan di Google Drive</a>
                            @endif
                            @if($document->status === 'failed')
                                <form method="POST" action="{{ route('pemberkasan.bookings.documents.retry', $document) }}">
                                    @csrf
                                    <button class="rounded-lg bg-zinc-900 px-3 py-2 text-sm font-medium text-white">Upload Ulang</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-sm text-zinc-500">Belum ada dokumen yang disinkronkan ke Google Drive.</p>
                @endforelse
            </div>
        </section>
        <form method="POST" action="{{ route('pemberkasan.bookings.update', $booking) }}" enctype="multipart/form-data" class="space-y-6 text-zinc-900">
            @csrf @method('PUT')
            <section class="rounded-xl border border-zinc-200 bg-white p-6"><h2 class="text-lg font-semibold">Data Customer & Dokumen</h2><div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach([['customer_name','Nama',$booking->customer->name],['customer_nik','NIK',$booking->customer->nik],['customer_birth_place','Tempat Lahir',$booking->customer->birth_place],['customer_occupation','Pekerjaan',$booking->customer->occupation],['customer_phone','Telepon',$booking->customer->phone],['customer_email','Email',$booking->customer->email]] as [$field,$label,$value])<div><label class="mb-2 block text-sm">{{ $label }}</label><input name="{{ $field }}" value="{{ old($field, $value) }}" class="w-full rounded-lg border px-3 py-2"></div>@endforeach
                <div>
                    <label for="customer_marital_status" class="mb-2 block text-sm">Status Perkawinan</label>
                    <select id="customer_marital_status" name="customer_marital_status" class="w-full rounded-lg border px-3 py-2">
                        <option value="">Pilih Status</option>
                        @foreach(['Belum Menikah', 'Menikah'] as $status)
                            <option value="{{ $status }}" @selected(old('customer_marital_status', $booking->customer->marital_status) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="mb-2 block text-sm">Tanggal Lahir</label><input type="date" name="customer_birth_date" value="{{ old('customer_birth_date', $booking->customer->birth_date?->format('Y-m-d')) }}" class="w-full rounded-lg border px-3 py-2"></div>
                <div class="sm:col-span-2"><label class="mb-2 block text-sm">Alamat</label><textarea name="customer_address" rows="2" class="w-full rounded-lg border px-3 py-2">{{ old('customer_address', $booking->customer->address) }}</textarea></div>
                @foreach(['ktp_file'=>'KTP','kk_file'=>'KK','npwp_file'=>'NPWP','booking_form_file'=>'Surat Pemesanan'] as $field => $label)
                    <div>
                        <label class="mb-2 block text-sm">{{ $label }}</label>
                        <input type="file" name="customer_{{ $field }}" accept=".jpg,.jpeg,.png,.pdf" class="w-full rounded-lg border px-3 py-2">
                        <p class="mt-1 text-xs text-zinc-500">Maksimal 10 MB.</p>
                        @if($booking->customer->{$field})
                            <a class="mt-1 block text-xs text-blue-600" href="{{ Storage::disk('public')->url($booking->customer->{$field}) }}" target="_blank">Lihat dokumen</a>
                        @endif
                    </div>
                @endforeach
            </div></section>

            <section class="rounded-xl border border-zinc-200 bg-white p-6"><h2 class="text-lg font-semibold">Checklist Berkas & BI Checking</h2><div class="mt-4 grid gap-4 sm:grid-cols-3"><div><label class="mb-2 block text-sm">Total Wajib</label><input type="number" name="document_total_required" min="0" value="{{ old('document_total_required', $booking->documentProcess?->total_required ?? 0) }}" class="w-full rounded-lg border px-3 py-2"></div><div><label class="mb-2 block text-sm">Total Selesai</label><input type="number" name="document_total_completed" min="0" value="{{ old('document_total_completed', $booking->documentProcess?->total_completed ?? 0) }}" class="w-full rounded-lg border px-3 py-2"></div><div><label class="mb-2 block text-sm">Status Berkas</label><select name="document_status" class="w-full rounded-lg border px-3 py-2">@foreach(['incomplete','complete','revision'] as $status)<option value="{{ $status }}" @selected(old('document_status', $booking->documentProcess?->status ?? 'incomplete') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div><div><label class="mb-2 block text-sm">BI Checking</label><select name="bi_checking_status" class="w-full rounded-lg border px-3 py-2">@foreach(['not_checked','processing','approved','rejected','revision'] as $status)<option value="{{ $status }}" @selected(old('bi_checking_status', $booking->documentProcess?->bi_checking_status ?? 'not_checked') === $status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></div><div><label class="mb-2 block text-sm">PIC</label><select name="document_pic_id" class="w-full rounded-lg border px-3 py-2"><option value="">Pilih PIC</option>@foreach($picUsers as $pic)<option value="{{ $pic->id }}" @selected(old('document_pic_id', $booking->documentProcess?->pic_id) == $pic->id)>{{ $pic->name }}</option>@endforeach</select></div><div><label class="mb-2 block text-sm">Catatan</label><input name="document_notes" value="{{ old('document_notes', $booking->documentProcess?->notes) }}" class="w-full rounded-lg border px-3 py-2"></div></div></section>

            <section class="rounded-xl border border-zinc-200 bg-white p-6"><h2 class="text-lg font-semibold">Proses Bank</h2><div class="mt-4 grid gap-4 sm:grid-cols-3"><div><label class="mb-2 block text-sm">Nama Bank</label><input name="bank_name" value="{{ old('bank_name', $booking->bankProcess?->bank_name ?? '') }}" class="w-full rounded-lg border px-3 py-2"></div><div><label class="mb-2 block text-sm">Status</label><select name="bank_status" class="w-full rounded-lg border px-3 py-2">@foreach(['not_submitted','submitted','processing','approved','rejected','revision'] as $status)<option value="{{ $status }}" @selected(old('bank_status', $booking->bankProcess?->status ?? 'not_submitted') === $status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></div><div><label class="mb-2 block text-sm">PIC</label><select name="bank_pic_id" class="w-full rounded-lg border px-3 py-2"><option value="">Pilih PIC</option>@foreach($picUsers as $pic)<option value="{{ $pic->id }}" @selected(old('bank_pic_id', $booking->bankProcess?->pic_id) == $pic->id)>{{ $pic->name }}</option>@endforeach</select></div><div class="sm:col-span-3"><label class="mb-2 block text-sm">Catatan</label><textarea name="bank_notes" rows="2" class="w-full rounded-lg border px-3 py-2">{{ old('bank_notes', $booking->bankProcess?->notes) }}</textarea></div></div></section>

            <section class="rounded-xl border border-zinc-200 bg-white p-6">
                <h2 class="text-lg font-semibold">SP3 dan Jadwal Akad</h2>
                @if(in_array($booking->akadSchedule?->status, ['scheduled', 'rescheduled'], true) && $booking->akadSchedule?->scheduled_at?->isPast())
                    <p role="status" class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">
                        Jadwal akad untuk {{ $booking->customer->name }} terlewati pada {{ $booking->akadSchedule->scheduled_at->format('d/m/Y H:i') }} dan belum ditandai selesai.
                    </p>
                @endif
                <div class="mt-4 grid gap-4 sm:grid-cols-2"><div><label class="mb-2 block text-sm">Nomor SP3</label><input name="sp3_number" value="{{ old('sp3_number', $booking->sp3?->sp3_number) }}" class="w-full rounded-lg border px-3 py-2"></div><div><label class="mb-2 block text-sm">Status SP3</label><select name="sp3_status" class="w-full rounded-lg border px-3 py-2">@foreach(['pending','issued','cancelled'] as $status)<option value="{{ $status }}" @selected(old('sp3_status', $booking->sp3?->status ?? 'pending') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div><div><label class="mb-2 block text-sm">Tanggal Terbit SP3</label><input type="date" name="sp3_issued_at" value="{{ old('sp3_issued_at', $booking->sp3?->issued_at?->format('Y-m-d')) }}" class="w-full rounded-lg border px-3 py-2"></div><div><label class="mb-2 block text-sm">Jadwal Akad</label><input type="datetime-local" name="akad_scheduled_at" value="{{ old('akad_scheduled_at', $booking->akadSchedule?->scheduled_at?->format('Y-m-d\\TH:i')) }}" class="w-full rounded-lg border px-3 py-2"></div><div><label class="mb-2 block text-sm">Status Akad</label><select name="akad_status" class="w-full rounded-lg border px-3 py-2">@foreach(['scheduled','completed','rescheduled','cancelled'] as $status)<option value="{{ $status }}" @selected(old('akad_status', $booking->akadSchedule?->status ?? 'scheduled') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div><div><label class="mb-2 block text-sm">Lokasi Akad</label><input name="akad_location" value="{{ old('akad_location', $booking->akadSchedule?->location) }}" class="w-full rounded-lg border px-3 py-2"></div><div class="sm:col-span-2"><label class="mb-2 block text-sm">Catatan Akad</label><textarea name="akad_notes" rows="2" class="w-full rounded-lg border px-3 py-2">{{ old('akad_notes', $booking->akadSchedule?->notes) }}</textarea></div></div>
            </section>
            <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">Simpan Data Customer & Pemberkasan</button>
        </form>
    </div>
</x-layouts::app>
