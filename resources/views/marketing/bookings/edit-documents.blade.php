<x-layouts::app :title="__('Edit Berkas Booking')">
    @php($documentsByType = $booking->pemberkasanDocuments->keyBy('document_type'))

    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <a href="{{ route('marketing.bookings.show', $booking) }}" class="text-sm text-zinc-500" wire:navigate>Kembali ke Detail Booking</a>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Edit Berkas Booking</h1>
                    <p class="mt-1 text-sm text-zinc-500">
                        {{ $booking->customer->name }} · {{ $booking->lot->block->property->name }} / {{ $booking->lot->block->name }}-{{ $booking->lot->lot_number }}
                    </p>
                </div>
                <a href="{{ route('marketing.bookings.edit', $booking) }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" wire:navigate>Edit Booking</a>
            </div>
        </div>

        @if(session('success'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        @if($errors->has('document_uploads'))
            <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first('document_uploads') }}</div>
        @endif

        <form method="POST" action="{{ route('marketing.bookings.documents.update', $booking) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            @foreach($documentGroups as $groupLabel => $documentTypes)
                <section class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $groupLabel }}</h2>
                        @if($groupLabel === 'Berkas BI Checking')
                            <p class="mt-1 text-sm text-zinc-500">Dokumen BI Checking dapat diunggah tanpa menunggu persetujuan.</p>
                        @elseif($groupLabel === 'Berkas Customer' || $groupLabel === 'Berkas Properti')
                            <p class="mt-1 text-sm text-zinc-500">Dokumen dapat diunggah sebelum BI Checking disetujui. Persetujuan tetap diperlukan untuk melanjutkan proses bank.</p>
                        @endif
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach($documentTypes as $documentIndex => $documentType)
                            @php($existingDocument = $documentsByType->get($documentType))
                            @php($inputId = 'document-upload-'.$loop->parent->index.'-'.$documentIndex)

                            <div class="space-y-2 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                <label for="{{ $inputId }}" class="block text-sm font-medium">{{ $documentType }}</label>

                                @if($existingDocument)
                                    <div class="text-xs text-zinc-500">
                                        <span>{{ $existingDocument->original_name }}</span>
                                        <span>·</span>
                                        @if($existingDocument->status === 'uploaded')
                                            <span class="font-medium text-emerald-700 dark:text-emerald-300">Tersimpan di Google Drive</span>
                                        @elseif($existingDocument->status === 'archived')
                                            <span class="font-medium text-zinc-500">Diarsipkan; file telah dihapus dari Google Drive</span>
                                        @elseif($existingDocument->status === 'failed')
                                            <span class="font-medium text-rose-700 dark:text-rose-300">Upload gagal, silakan pilih file untuk mencoba kembali</span>
                                        @else
                                            <span class="font-medium text-amber-700 dark:text-amber-300">{{ $existingDocument->status === 'uploading' ? 'Sedang diunggah' : 'Menunggu upload Google Drive' }}</span>
                                        @endif
                                        @if($existingDocument->drive_url && $existingDocument->status === 'uploaded')
                                            · <a href="{{ $existingDocument->drive_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-blue-700 hover:underline dark:text-blue-300">Buka</a>
                                        @endif
                                    </div>
                                @else
                                    <p class="text-xs text-zinc-500">Belum ada berkas yang diunggah.</p>
                                @endif

                                <input id="{{ $inputId }}" type="file" name="document_uploads[{{ $documentType }}]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                <p class="text-xs text-zinc-500">PDF/JPG/PNG, maksimal 10 MB. Kosongkan jika tidak mengunggah atau mengganti dokumen ini.</p>
                                @error('document_uploads.'.$documentType)
                                    <p class="text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-zinc-500">Pilih satu atau beberapa file untuk diunggah. Dokumen yang sudah tersimpan tetap terhubung dengan booking ini.</p>
                <button type="submit" class="rounded-lg bg-orange-500 px-6 py-3 text-base font-bold text-white shadow-md transition hover:bg-orange-600 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-orange-300 dark:focus:ring-orange-800">Simpan Berkas</button>
            </div>
        </form>
    </div>
</x-layouts::app>
