<x-layouts::app :title="__('Kelola Berkas Booking')">
    <div class="mx-auto max-w-5xl space-y-6 text-zinc-900">
        <div><a href="{{ route('pemberkasan.bookings.index') }}" class="text-sm text-zinc-500" wire:navigate>Kembali</a><h1 class="mt-3 text-2xl font-semibold">{{ $booking->customer->name }} · {{ $booking->lot->lot_number }}</h1><p class="mt-1 text-sm text-zinc-500">{{ $booking->lot->block->property->name }} / {{ $booking->lot->block->name }} · Sales: {{ $booking->sales?->name ?? '-' }}</p></div>
        @if(session('success'))<div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @php
            $documentGroups = [
                'BI Checking' => \App\Services\PemberkasanDocumentService::BI_CHECKING_DOCUMENT_TYPES,
                'Berkas Customer' => \App\Services\PemberkasanDocumentService::ADDITIONAL_MARKETING_DOCUMENT_TYPES,
                'Berkas Properti' => \App\Services\PemberkasanDocumentService::PROPERTY_DOCUMENT_TYPES,
            ];
            $documentsByType = $booking->pemberkasanDocuments->keyBy('document_type');
            $legacyDocumentTypes = [
                'KTP Pemohon' => 'KTP',
                'Kartu Keluarga' => 'KK',
            ];
        @endphp
        <section class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6">
            <div>
                <h2 class="text-lg font-semibold">Berkas Booking</h2>
                <p class="mt-1 text-sm text-zinc-500">Daftar berkas yang belum tersedia. Unggah semua dokumen dari bagian Data Customer & Dokumen di bawah.</p>
            </div>
            @php
                $missingDocuments = collect($documentGroups)
                    ->map(fn (array $documentTypes): array => collect($documentTypes)
                        ->filter(function (string $documentType) use ($documentsByType, $legacyDocumentTypes): bool {
                            $document = $documentsByType->get($documentType)
                                ?? $documentsByType->get($legacyDocumentTypes[$documentType] ?? '');

                            return $document?->status !== 'uploaded';
                        })
                        ->values()
                        ->all())
                    ->filter(fn (array $documentTypes): bool => $documentTypes !== []);
            @endphp
            @if($missingDocuments->isEmpty())
                <p class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-800">Semua berkas yang diwajibkan sudah tersedia di Google Drive.</p>
            @else
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach($missingDocuments as $groupLabel => $documentTypes)
                        <section class="rounded-lg border border-amber-200 bg-amber-50/60 p-4">
                            <h3 class="font-semibold">{{ $groupLabel }}</h3>
                            <ul class="mt-3 list-inside list-disc space-y-2 text-sm text-zinc-700">
                                @foreach($documentTypes as $documentType)
                                    <li>
                                        {{ $documentType }}
                                        @php($document = $documentsByType->get($documentType) ?? $documentsByType->get($legacyDocumentTypes[$documentType] ?? ''))
                                        @if($document?->status === 'failed')
                                            <span class="text-red-700">— upload gagal</span>
                                        @elseif($document)
                                            <span class="text-amber-700">— belum tersimpan</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            @endif
        </section>
        <form method="POST" action="{{ route('pemberkasan.bookings.update', $booking) }}" enctype="multipart/form-data" class="space-y-6 text-zinc-900">
            @csrf @method('PUT')
            <section class="rounded-xl border border-zinc-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Data Customer & Dokumen</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
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
                @foreach([
                    'Lokasi kerja pemohon' => $booking->applicant_work_location_url,
                    'Lokasi kerja pasangan' => $booking->spouse_work_location_url,
                ] as $locationLabel => $locationUrl)
                    <div class="rounded-lg bg-zinc-50 p-3">
                        <p class="text-xs text-zinc-500">{{ $locationLabel }}</p>
                        @if($locationUrl)
                            <a href="{{ $locationUrl }}" target="_blank" rel="noopener noreferrer" class="mt-1 block break-all text-sm font-medium text-blue-600 hover:underline">Buka Google Maps</a>
                        @else
                            <p class="mt-1 text-sm">Belum diisi Marketing</p>
                        @endif
                    </div>
                @endforeach
                </div>
                <div class="mt-6 space-y-5 border-t border-zinc-200 pt-5">
                    @foreach($documentGroups as $groupLabel => $documentTypes)
                        <section>
                            <h3 class="font-semibold">{{ $groupLabel }}</h3>
                            @if($groupLabel !== 'BI Checking')
                                <p class="mt-1 text-sm text-zinc-500">Berkas dapat diunggah kapan saja. Persetujuan BI Checking diperlukan sebelum melanjutkan ke proses bank.</p>
                            @endif
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                @foreach($documentTypes as $documentType)
                                    @php($driveDocument = $documentsByType->get($documentType) ?? $documentsByType->get($legacyDocumentTypes[$documentType] ?? ''))
                                    <div class="rounded-lg border border-zinc-200 p-4">
                                        <label for="document-upload-{{ $loop->parent->index }}-{{ $loop->index }}" class="mb-2 block text-sm font-medium">{{ $documentType }}</label>
                                        <input
                                            id="document-upload-{{ $loop->parent->index }}-{{ $loop->index }}"
                                            type="file"
                                            name="document_uploads[{{ $documentType }}]"
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm"
                                        >
                                        <p class="mt-1 text-xs text-zinc-500">PDF/JPG/PNG, maksimal 10 MB.</p>
                                        @if($driveDocument?->status === 'uploaded')
                                            <div class="mt-2 flex items-center justify-between gap-2">
                                                <span class="text-xs font-medium text-green-700">Tersimpan di Google Drive</span>
                                                @if($driveDocument->drive_url)
                                                    <a href="{{ $driveDocument->drive_url }}" target="_blank" rel="noopener noreferrer" class="text-xs font-medium text-blue-600 hover:underline">Lihat dokumen</a>
                                                @endif
                                            </div>
                                        @elseif($driveDocument?->status === 'failed')
                                            <p class="mt-2 text-xs font-medium text-red-700">Upload gagal. Pilih file lagi untuk mencoba kembali.</p>
                                        @elseif($driveDocument)
                                            <p class="mt-2 text-xs font-medium text-amber-700">Dokumen belum tersimpan di Google Drive.</p>
                                        @endif
                                        @error('document_uploads.'.$documentType)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
                <p class="mt-4 text-xs text-zinc-500">File yang dipilih akan langsung diunggah ke folder booking terkait di Google Drive saat formulir disimpan.</p>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Checklist Berkas & BI Checking</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="document-total-required" class="mb-2 block text-sm">Total Wajib</label>
                        <input id="document-total-required" type="number" name="document_total_required" min="0" value="{{ old('document_total_required', $booking->documentProcess?->total_required ?? 0) }}" class="w-full rounded-lg border px-3 py-2">
                    </div>
                    <div>
                        <label for="document-total-completed" class="mb-2 block text-sm">Total Selesai</label>
                        <input id="document-total-completed" type="number" name="document_total_completed" min="0" value="{{ old('document_total_completed', $booking->documentProcess?->total_completed ?? 0) }}" class="w-full rounded-lg border px-3 py-2">
                    </div>
                    <div>
                        <label for="document-status" class="mb-2 block text-sm">Status Berkas</label>
                        <select id="document-status" name="document_status" class="w-full rounded-lg border px-3 py-2">
                            @foreach(['incomplete', 'complete', 'revision'] as $status)
                                <option value="{{ $status }}" @selected(old('document_status', $booking->documentProcess?->status ?? 'incomplete') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="bi-checking-status" class="mb-2 block text-sm">BI Checking</label>
                        @if(auth()->user()->isAdmin() || in_array(auth()->user()->role, ['tl_pemberkasan', 'staff_pemberkasan'], true))
                            <select id="bi-checking-status" name="bi_checking_status" class="w-full rounded-lg border px-3 py-2">
                                @foreach(['not_checked', 'processing', 'approved', 'rejected', 'revision'] as $status)
                                    <option value="{{ $status }}" @selected(old('bi_checking_status', $booking->documentProcess?->bi_checking_status ?? 'not_checked') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                        @else
                            <p id="bi-checking-status" class="rounded-lg border bg-zinc-50 px-3 py-2 text-sm">{{ ucfirst(str_replace('_', ' ', $booking->documentProcess?->bi_checking_status ?? 'not_checked')) }} · diverifikasi oleh Staff Pemberkasan</p>
                        @endif
                    </div>
                    <div>
                        <label for="document-pic" class="mb-2 block text-sm">PIC</label>
                        <select id="document-pic" name="document_pic_id" class="w-full rounded-lg border px-3 py-2">
                            <option value="">Pilih PIC</option>
                            @foreach($picUsers as $pic)
                                <option value="{{ $pic->id }}" @selected(old('document_pic_id', $booking->documentProcess?->pic_id) == $pic->id)>{{ $pic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="document-notes" class="mb-2 block text-sm">Catatan</label>
                        <input id="document-notes" name="document_notes" value="{{ old('document_notes', $booking->documentProcess?->notes) }}" class="w-full rounded-lg border px-3 py-2">
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Proses Bank</h2>
                @if(($booking->documentProcess?->bi_checking_status ?? 'not_checked') !== 'approved')
                    <p role="status" class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">Proses bank dan tahap berikutnya baru dapat dilanjutkan setelah BI Checking disetujui.</p>
                @endif
                <div class="mt-4 grid gap-4 sm:grid-cols-3"><div><label class="mb-2 block text-sm">Nama Bank</label><input name="bank_name" value="{{ old('bank_name', $booking->bankProcess?->bank_name ?? '') }}" class="w-full rounded-lg border px-3 py-2"></div><div><label class="mb-2 block text-sm">Status</label><select name="bank_status" class="w-full rounded-lg border px-3 py-2">@foreach(['not_submitted','submitted','processing','approved','rejected','revision'] as $status)<option value="{{ $status }}" @selected(old('bank_status', $booking->bankProcess?->status ?? 'not_submitted') === $status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></div><div><label class="mb-2 block text-sm">PIC</label><select name="bank_pic_id" class="w-full rounded-lg border px-3 py-2"><option value="">Pilih PIC</option>@foreach($picUsers as $pic)<option value="{{ $pic->id }}" @selected(old('bank_pic_id', $booking->bankProcess?->pic_id) == $pic->id)>{{ $pic->name }}</option>@endforeach</select></div><div class="sm:col-span-3"><label class="mb-2 block text-sm">Catatan</label><textarea name="bank_notes" rows="2" class="w-full rounded-lg border px-3 py-2">{{ old('bank_notes', $booking->bankProcess?->notes) }}</textarea></div></div>
            </section>

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
