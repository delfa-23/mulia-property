<x-layouts::app :title="__('Berkas Lainnya')">
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <a href="{{ route('pemberkasan.bookings.index') }}" class="text-sm text-zinc-500" wire:navigate>Kembali ke daftar booking</a>
            <h1 class="mt-3 text-2xl font-semibold">Berkas Lainnya</h1>
            <p class="mt-1 text-sm text-zinc-500">Berkas terhubung ke booking dan tersimpan bersama agar dapat dilihat oleh Pemberkasan dan Marketing.</p>
        </div>

        @if(session('success'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('pemberkasan.other-documents.store') }}"
            enctype="multipart/form-data"
            class="grid gap-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-sm sm:grid-cols-2 dark:border-zinc-700 dark:bg-zinc-900"
        >
            @csrf
            <h2 class="text-lg font-semibold sm:col-span-2">Unggah berkas tambahan</h2>
            <div class="sm:col-span-2">
                <label for="other-document-booking" class="mb-1 block text-sm font-medium">Booking / Customer / Perumahan</label>
                <select id="other-document-booking" name="booking_id" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                    <option value="">Pilih booking</option>
                    @foreach($bookings as $booking)
                        <option value="{{ $booking->id }}" @selected(old('booking_id') == $booking->id)>
                            {{ $booking->customer?->name ?? 'Customer' }} · {{ $booking->lot?->block?->property?->name ?? 'Perumahan' }} / {{ $booking->lot?->block?->name }}-{{ $booking->lot?->lot_number }}
                            — BI Checking: {{ ucfirst(str_replace('_', ' ', $booking->documentProcess?->bi_checking_status ?? 'not_checked')) }}
                        </option>
                    @endforeach
                </select>
                @error('booking_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-zinc-500">Berkas dapat diunggah sebelum atau sesudah BI Checking disetujui. Persetujuan BI Checking tetap diperlukan sebelum proses bank.</p>
            </div>
            <div>
                <label for="other-document-type" class="mb-1 block text-sm font-medium">Jenis berkas</label>
                <input id="other-document-type" name="document_type" value="{{ old('document_type') }}" maxlength="100" required placeholder="Contoh: Surat Keterangan Kerja" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                @error('document_type')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="other-document-file" class="mb-1 block text-sm font-medium">File</label>
                <input id="other-document-file" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                <p class="mt-1 text-xs text-zinc-500">PDF/JPG/PNG, maksimal 10 MB.</p>
                @error('document')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-end sm:col-span-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">Unggah berkas</button>
            </div>
        </form>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 p-5 dark:border-zinc-700">
                <h2 class="text-lg font-semibold">Riwayat Berkas Lainnya</h2>
            </div>
            <form method="GET" action="{{ route('pemberkasan.other-documents.index') }}" class="grid gap-4 border-b border-zinc-200 bg-zinc-50 p-5 sm:grid-cols-2 xl:grid-cols-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                <div>
                    <label for="document-search" class="mb-1 block text-sm font-medium">Cari</label>
                    <input id="document-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Customer, kavling, atau nama file" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                </div>
                <div>
                    <label for="document-property" class="mb-1 block text-sm font-medium">Perumahan</label>
                    <select id="document-property" name="property_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                        <option value="">Semua perumahan</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" @selected(($filters['property_id'] ?? null) == $property->id)>{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="document-type" class="mb-1 block text-sm font-medium">Jenis berkas</label>
                    <select id="document-type" name="document_type" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                        <option value="">Semua jenis</option>
                        @foreach($documentTypes as $documentType)
                            <option value="{{ $documentType }}" @selected(($filters['document_type'] ?? null) === $documentType)>{{ $documentType }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="document-status" class="mb-1 block text-sm font-medium">Status upload</label>
                    <select id="document-status" name="status" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                        <option value="">Semua status</option>
                        @foreach(['pending' => 'Menunggu upload', 'uploading' => 'Sedang diunggah', 'uploaded' => 'Tersimpan', 'failed' => 'Gagal'] as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" @selected(($filters['status'] ?? null) === $statusValue)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-4">
                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Terapkan filter</button>
                    @if($filters !== [])
                        <a href="{{ route('pemberkasan.other-documents.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-white dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">Reset</a>
                    @endif
                </div>
            </form>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($documents as $document)
                    <article class="flex flex-col gap-3 p-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $document->document_type }} <span class="font-normal text-zinc-500">· {{ $document->original_name }}</span></p>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $document->booking?->customer?->name ?? 'Customer tidak tersedia' }}
                                · {{ $document->booking?->lot?->block?->property?->name ?? 'Perumahan tidak tersedia' }}
                                · {{ $document->booking?->lot?->block?->name }}-{{ $document->booking?->lot?->lot_number }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-500">
                                Diunggah oleh {{ $document->uploadedBy?->name ?? 'Pengguna tidak tersedia' }}
                                @if($document->uploaded_at)
                                    · {{ $document->uploaded_at->format('d/m/Y H:i') }}
                                @endif
                            </p>
                            @if($document->status === 'uploaded')
                                <p class="mt-2 text-sm font-medium text-emerald-700 dark:text-emerald-300">Tersimpan di Google Drive</p>
                            @elseif($document->status === 'archived')
                                <p class="mt-2 text-sm font-medium text-zinc-500">Diarsipkan. File telah dihapus dari Google Drive karena kavling dialihkan.</p>
                            @elseif($document->status === 'failed')
                                <p class="mt-2 text-sm font-medium text-rose-700 dark:text-rose-300">Upload ke Google Drive gagal</p>
                                @if($document->upload_error)
                                    <p class="mt-1 text-sm text-rose-600">{{ $document->upload_error }}</p>
                                @endif
                            @else
                                <p class="mt-2 text-sm font-medium text-amber-700 dark:text-amber-300">{{ $document->status === 'uploading' ? 'Sedang diunggah' : 'Menunggu upload Google Drive' }}</p>
                            @endif
                        </div>
                        @if($document->drive_url && $document->status === 'uploaded')
                            <a href="{{ $document->drive_url }}" target="_blank" rel="noopener noreferrer" class="shrink-0 text-sm font-medium text-blue-700 hover:underline dark:text-blue-300">Buka berkas</a>
                        @endif
                    </article>
                @empty
                    <p class="p-5 text-sm text-zinc-500">Belum ada berkas tambahan.</p>
                @endforelse
            </div>
            <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">{{ $documents->links() }}</div>
        </section>
    </div>
</x-layouts::app>
