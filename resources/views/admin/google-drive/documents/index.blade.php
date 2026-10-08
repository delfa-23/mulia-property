<x-layouts::app :title="__('Google Drive Documents')">
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Dokumen Google Drive</h1>
                <p class="mt-1 text-sm text-zinc-500">Pilih customer untuk melihat dan mengelola dokumennya.</p>
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

        <form method="GET" action="{{ route('admin.google-drive.documents.index') }}" class="grid gap-4 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-4 xl:items-end">
            <div class="flex flex-col gap-1.5">
                <label for="property_id" class="text-sm font-medium text-zinc-700">Perumahan</label>
                <select
                    id="property_id"
                    name="property_id"
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800"
                >
                    <option value="">Semua Perumahan</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" @selected((string) request('property_id') === (string) $property->id)>
                            {{ $property->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="lot_id" class="text-sm font-medium text-zinc-700">Blok / Kavling</label>
                <select
                    id="lot_id"
                    name="lot_id"
                    @disabled(! request('property_id'))
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500"
                >
                    <option value="">Semua Blok / Kavling</option>
                    @foreach($lotOptions as $lotOption)
                        <option value="{{ $lotOption->id }}" @selected((string) request('lot_id') === (string) $lotOption->id)>
                            {{ $lotOption->block->name }} / {{ $lotOption->lot_number }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="customer_id" class="text-sm font-medium text-zinc-700">Nama Customer</label>
                <select
                    id="customer_id"
                    name="customer_id"
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800"
                >
                    <option value="">Semua Customer</option>
                    @foreach($customerOptions as $customerOption)
                        <option value="{{ $customerOption->id }}" @selected((string) request('customer_id') === (string) $customerOption->id)>
                            {{ $customerOption->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700">
                    Filter
                </button>
                <a href="{{ route('admin.google-drive.documents.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                    Reset
                </a>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-4 py-3">Nama Customer</th>
                            <th class="px-4 py-3">Perumahan / Blok / Kavling</th>
                            <th class="px-4 py-3">Jumlah Dokumen</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse($customers as $customer)
                            <tr>
                                <td class="px-4 py-4 font-medium text-zinc-900">{{ $customer->name }}</td>
                                <td class="px-4 py-4 text-zinc-600">
                                    <div class="flex flex-col gap-2">
                                        @forelse($customer->pemberkasanDocuments as $document)
                                            <div>
                                                <p class="font-medium text-zinc-800">
                                                    {{ $document->property?->name ?? $document->lot?->block?->property?->name ?? '-' }}
                                                </p>
                                                <p class="text-xs text-zinc-500">
                                                    Blok {{ $document->lot?->block?->name ?? '-' }} / Kavling {{ $document->lot?->lot_number ?? '-' }}
                                                </p>
                                            </div>
                                        @empty
                                            <span>-</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-zinc-600">{{ $customer->documents_count }}</td>
                                <td class="px-4 py-4 text-right">
                                    <a
                                        href="{{ route('admin.google-drive.documents.customer', $customer) }}"
                                        class="inline-flex items-center rounded-lg bg-zinc-900 px-3 py-2 text-xs font-semibold text-white hover:bg-zinc-700"
                                    >
                                        Lihat Dokumen
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-zinc-500">Tidak ada customer dengan dokumen yang sesuai filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-4">{{ $customers->links() }}</div>
        </div>
    </div>
</x-layouts::app>
