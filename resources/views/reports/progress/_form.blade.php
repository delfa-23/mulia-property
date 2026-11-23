<div>
    <div>
        <label for="report_date" class="mb-2 block text-sm font-medium">Tanggal Pembuatan Laporan</label>
        <input id="report_date" type="date" name="report_date" value="{{ old('report_date', $progressReport?->report_date?->format('Y-m-d') ?? now()->toDateString()) }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        @error('report_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label for="title" class="mb-2 block text-sm font-medium">Judul</label>
    <input id="title" name="title" value="{{ old('title', $progressReport?->title) }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
    @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

@if($isConstructionReport)
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="property_id" class="mb-2 block text-sm font-medium">Perumahan</label>
            <select id="property_id" name="property_id" data-property-select required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                <option value="">Pilih perumahan</option>
                @foreach($properties as $property)
                    <option value="{{ $property->id }}" @selected(old('property_id', $progressReport?->property_id) == $property->id)>{{ $property->name }}</option>
                @endforeach
            </select>
            @error('property_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="lot_id" class="mb-2 block text-sm font-medium">Blok / Kavling</label>
            <select id="lot_id" name="lot_id" data-lot-select required disabled class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                <option value="">Pilih kavling</option>
                @foreach($lots as $lot)
                    <option value="{{ $lot->id }}" data-property-id="{{ $lot->block->property_id }}" @selected(old('lot_id', $progressReport?->lot_id) == $lot->id)>
                        {{ $lot->block->name }}/{{ $lot->lot_number }}
                    </option>
                @endforeach
            </select>
            @error('lot_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div data-camera-photo-form data-photo-required="{{ $progressReport?->photo_path ? 'false' : 'true' }}" class="space-y-3 rounded-lg border border-zinc-200 p-4">
        <div>
            <p class="text-sm font-semibold">Foto langsung di lapangan</p>
            <p class="mt-1 text-xs text-zinc-500">Gunakan kamera perangkat. Pilihan foto dari galeri atau file tidak tersedia.</p>
        </div>

        @if($progressReport?->photo_path)
            <div class="space-y-2">
                <p class="text-xs font-medium text-zinc-600">Foto laporan saat ini</p>
                <img src="{{ $progressReport->photoUrl() }}" alt="Foto progress kavling" class="max-h-64 rounded-lg object-contain">
            </div>
        @endif

        <video data-camera-preview playsinline autoplay muted hidden class="max-h-80 w-full rounded-lg bg-black object-contain"></video>
        <img data-camera-captured-image alt="Pratinjau foto progress" hidden class="max-h-80 w-full rounded-lg object-contain">
        <canvas data-camera-canvas hidden></canvas>
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-camera-photo hidden>
        @error('photo')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <p data-camera-error role="status" class="text-sm text-red-600" hidden></p>

        <div class="flex flex-wrap gap-2">
            <button type="button" data-camera-start class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium">Buka Kamera</button>
            <button type="button" data-camera-capture disabled class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Ambil Foto</button>
            <button type="button" data-camera-retake hidden class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium">Ambil Ulang</button>
        </div>
        <p data-camera-required-message class="text-sm text-red-600" hidden>Ambil foto lapangan sebelum menyimpan laporan.</p>
    </div>
@endif

<div>
    <label for="content" class="mb-2 block text-sm font-medium">Isi Laporan</label>
    <textarea id="content" name="content" rows="8" class="w-full rounded-lg border border-zinc-300 px-3 py-2">{{ old('content', $progressReport?->content) }}</textarea>
    @error('content')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
