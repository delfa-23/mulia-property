<x-layouts::app :title="__('Kirim Alert ke Admin')">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <a href="{{ route('dashboard') }}" class="text-sm text-zinc-500" wire:navigate>Kembali ke dashboard</a>
            <h1 class="mt-3 text-2xl font-semibold">Kirim Alert ke Admin</h1>
            <p class="mt-1 text-sm text-zinc-500">Gunakan alert untuk melaporkan kondisi penting yang membutuhkan perhatian atau keputusan Admin.</p>
        </div>

        @if(session('success'))<div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ route('alerts.store') }}" class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium">Jenis Alert</label>
                    <select name="type" required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                        @foreach(['operational' => 'Operasional', 'booking' => 'Booking', 'document' => 'Pemberkasan', 'finance' => 'Keuangan', 'construction' => 'Pembangunan', 'other' => 'Lainnya'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Tingkat Urgensi</label>
                    <select name="severity" required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                        @foreach(['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'critical' => 'Kritis'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('severity', 'medium') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('severity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium">Perumahan</label>
                    <select name="property_filter" data-property-select class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                        <option value="">Pilih perumahan</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" @selected(old('property_filter') == $property->id)>{{ $property->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">Kavling Terkait <span class="font-normal text-zinc-500">(opsional)</span></label>
                    <select name="lot_id" data-lot-select disabled class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                        <option value="">Tidak terkait kavling tertentu</option>
                        @foreach($lots as $lot)
                            <option value="{{ $lot->id }}" data-property-id="{{ $lot->block->property_id }}" @selected(old('lot_id') == $lot->id)>{{ $lot->block->property->name }} / {{ $lot->block->name }} / {{ $lot->lot_number }}</option>
                        @endforeach
                    </select>
                    @error('lot_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium">Judul Alert</label>
                <input name="title" value="{{ old('title') }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2" placeholder="Contoh: Dokumen booking belum lengkap">
                @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium">Detail Masalah</label>
                <textarea name="message" rows="5" required class="w-full rounded-lg border border-zinc-300 px-3 py-2" placeholder="Jelaskan kondisi, dampak, dan tindakan yang dibutuhkan Admin.">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex justify-end">
                <button class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">Kirim Alert</button>
            </div>
        </form>
    </div>
</x-layouts::app>
