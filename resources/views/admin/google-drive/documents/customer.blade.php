<x-layouts::app :title="__('Customer Documents')">
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('admin.google-drive.documents.index') }}" class="text-sm font-medium text-blue-700 hover:underline">
                    &larr; Kembali ke daftar customer
                </a>
                <h1 class="mt-2 text-2xl font-semibold">Dokumen {{ $customer->name }}</h1>
                <p class="mt-1 text-sm text-zinc-500">Kelola dokumen Google Drive milik customer ini.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full px-3 py-1 text-sm font-medium {{ $googleDriveConnected ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $googleDriveConnected ? 'Terhubung: '.$googleDriveAccountEmail : 'Belum terhubung ke '.$googleDriveAccountEmail }}
                </span>
                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('google.drive.redirect') }}" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white">
                        {{ $googleDriveConnected ? 'Hubungkan Ulang' : 'Hubungkan Google Drive' }}
                    </a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-270 table-fixed text-left text-sm">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="w-36 px-4 py-3">Jenis Dokumen</th>
                            <th class="w-48 px-4 py-3">Perumahan / Kavling</th>
                            <th class="w-36 px-4 py-3">Status</th>
                            <th class="w-40 px-4 py-3">Upload Drive</th>
                            <th class="w-72 px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse($documents as $document)
                            <tr class="align-top">
                                <td class="wrap-break-word px-4 py-4">
                                    <span class="font-medium">{{ $document->document_type }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="wrap-break-word">{{ $document->property?->name ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">
                                        {{ $document->lot?->block?->name ?? '-' }} / {{ $document->lot?->lot_number ?? '-' }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-green-100 text-green-800' => $document->status === 'uploaded',
                                        'bg-red-100 text-red-800' => $document->status === 'failed',
                                        'bg-amber-100 text-amber-800' => in_array($document->status, ['pending', 'uploading'], true),
                                        'bg-zinc-100 text-zinc-700' => ! in_array($document->status, ['uploaded', 'failed', 'pending', 'uploading'], true),
                                    ])>
                                        {{ match ($document->status) {
                                            'uploaded' => 'Berhasil',
                                            'failed' => 'Gagal',
                                            'uploading' => 'Sedang upload',
                                            'pending' => 'Menunggu',
                                            default => ucfirst($document->status),
                                        } }}
                                    </span>
                                    @if($document->upload_error)
                                        <p class="mt-2 max-w-xs text-xs text-red-600">{{ $document->upload_error }}</p>
                                    @endif
                                </td>
                                <td class="wrap-break-word px-4 py-4">
                                    {{ $document->uploaded_at?->format('d M Y H:i') ?? '-' }}
                                    @if($document->retry_count > 0)
                                        <p class="mt-1 text-xs text-zinc-500">Retry manual: {{ $document->retry_count }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-col items-start gap-3">
                                        @if($document->drive_folder_id && $document->status === 'uploaded')
                                            <a href="https://drive.google.com/drive/folders/{{ $document->drive_folder_id }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100">Buka Folder di Drive</a>
                                        @endif
                                        @if($document->status === 'failed')
                                            <form method="POST" action="{{ route('admin.google-drive.documents.retry', $document) }}">
                                                @csrf
                                                <button class="rounded-lg bg-zinc-900 px-3 py-2 text-xs font-medium text-white">Upload Ulang</button>
                                            </form>
                                        @endif
                                        @if(in_array($document->status, ['pending', 'uploading'], true))
                                            <p class="max-w-56 text-xs text-amber-700">
                                                Penggantian dan penghapusan tersedia setelah proses upload selesai.
                                            </p>
                                        @else
                                            <form
                                                method="POST"
                                                action="{{ route('admin.google-drive.documents.replace', $document) }}"
                                                enctype="multipart/form-data"
                                                class="flex w-full max-w-64 flex-col items-start gap-2 rounded-lg border border-zinc-200 p-3"
                                            >
                                                @csrf
                                                @method('PUT')
                                                <label class="text-xs font-medium text-zinc-700">Maks. 10 MB</label>
                                                <input
                                                    type="file"
                                                    name="document"
                                                    accept=".pdf,.jpg,.jpeg,.png"
                                                    required
                                                    class="w-full min-w-0 cursor-pointer rounded-lg border border-zinc-300 bg-white text-xs text-zinc-600 file:mr-3 file:cursor-pointer file:rounded-l-lg file:border-0 file:border-r file:border-solid file:border-zinc-300 file:bg-zinc-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-zinc-800 hover:file:bg-zinc-200"
                                                >
                                                <button class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-medium text-zinc-700 hover:bg-zinc-50">
                                                    Upload Pengganti
                                                </button>
                                            </form>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.google-drive.documents.destroy', $document) }}"
                                                onsubmit="return confirm('Hapus dokumen ini dari Google Drive dan aplikasi?')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-lg border border-red-200 px-3 py-2 text-xs font-medium text-red-700 hover:bg-red-50">
                                                    Hapus Dokumen
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-zinc-500">Customer ini belum memiliki dokumen Pemberkasan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-4">{{ $documents->links() }}</div>
        </div>
    </div>
</x-layouts::app>
