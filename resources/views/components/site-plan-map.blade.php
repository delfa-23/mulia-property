@props([
    'property' => null,
    'lots' => collect(),
    'areas' => collect(),
    'selectedLotId' => null,
    'manageUrl' => null,
    'dashboardUrl',
    'detailMode' => 'panel',
    'managementRole' => null,
    'wide' => false,
])

@php
    $lotStatuses = config('lot_statuses');
    $mappedLotIds = $areas->whereNotNull('lot_id')->pluck('lot_id');
    $legacyMappedLots = $lots
        ->filter(fn ($lot): bool => is_array($lot->map_coordinates) && ! $mappedLotIds->contains($lot->id))
        ->values();
    $mappedLotCount = $mappedLotIds->merge($legacyMappedLots->pluck('id'))->unique()->count();
@endphp

<section id="site-plan" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-[#10233D]">Denah Perumahan</h2>
            <p class="mt-1 text-sm text-slate-500">
                @if($property)
                    {{ $property->name }} · klik area kavling untuk melihat detail
                @else
                    Pilih perumahan untuk menampilkan denah kavling.
                @endif
            </p>
        </div>
        @if($manageUrl)
            <a href="{{ $manageUrl }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-[#10233D] hover:bg-slate-50">
                Kelola Denah
            </a>
        @endif
    </div>

    @if(!$property)
        <p class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
            Pilih perumahan pada filter di atas, lalu tekan Terapkan untuk melihat denahnya.
        </p>
    @elseif(!$property->site_plan_path)
        <p class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
            Denah belum diunggah untuk perumahan ini.
            @if($manageUrl)
                <a href="{{ $manageUrl }}" class="font-semibold text-blue-700 underline">Unggah denah</a>.
            @endif
        </p>
    @else
        <div class="grid gap-4 {{ $wide ? 'grid-cols-1' : 'xl:grid-cols-[minmax(0,1fr)_18rem]' }}" data-site-plan-viewer data-property-id="{{ $property->id }}" data-dashboard-url="{{ $dashboardUrl }}">
            <div class="space-y-2">
                <div class="flex items-center gap-2" aria-label="Kontrol zoom siteplan">
                    <button type="button" data-site-plan-zoom-in class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700">Zoom +</button>
                    <button type="button" data-site-plan-zoom-out class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700">Zoom −</button>
                    <button type="button" data-site-plan-zoom-reset class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700">Reset</button>
                    <span data-site-plan-zoom-label class="text-xs tabular-nums text-slate-500">100%</span>
                </div>
                <div data-site-plan-viewport class="min-w-0 overflow-auto rounded-lg border border-slate-200 bg-slate-50 p-2">
                    <div data-site-plan-zoom-content class="relative min-w-160" style="width: 100%;">
                        <img src="{{ $property->sitePlanUrl() }}" alt="Denah {{ $property->name }}" class="block h-auto w-full select-none" draggable="false">
                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 h-full w-full overflow-visible" aria-label="Area siteplan">
                            @foreach($areas as $area)
                                @php
                                    $lot = $area->lot;
                                    if ($lot && (int) $lot->block->property_id !== (int) $property->id) {
                                        $lot = null;
                                    }
                                    $status = $lot
                                        ? ($lotStatuses[$lot->status] ?? ['label' => ucfirst($lot->status), 'color' => '#64748b', 'fill' => '#94a3b8'])
                                        : ['label' => 'Belum tertaut', 'color' => '#475569', 'fill' => '#cbd5e1'];
                                    $lotLabel = $lot ? $lot->block->name.'/'.$lot->lot_number : ($area->label ?: 'Area belum tertaut');
                                    $points = implode(' ', array_map(
                                        fn (array $point): string => number_format($point['x'], 4, '.', '').','.number_format($point['y'], 4, '.', ''),
                                        $area->polygon,
                                    ));
                                    $booking = $lot?->bookings->first();
                                    $editableBooking = $booking && $booking->status !== 'finish' ? $booking : null;
                                @endphp
                                <polygon
                                    data-site-plan-lot
                                    data-area-id="{{ $area->id }}"
                                    data-lot-id="{{ $lot?->id }}"
                                    data-lot-mapped="{{ $lot ? 'true' : 'false' }}"
                                    data-lot-label="{{ $lotLabel }}"
                                    data-lot-status="{{ $status['label'] }}"
                                    data-lot-color="{{ $status['color'] }}"
                                    data-lot-price="{{ $lot?->house_price }}"
                                    data-lot-notes="{{ $lot?->notes }}"
                                    data-lot-images="{{ json_encode(array_column($lot?->imageGallery() ?? [], 'url')) }}"
                                    data-lot-property="{{ $property->name }}"
                                    data-lot-block="{{ $lot?->block->name }}"
                                    data-lot-number="{{ $lot?->lot_number }}"
                                    data-lot-customer="{{ $booking?->customer?->name }}"
                                    data-lot-sales="{{ $booking?->sales?->name }}"
                                    data-lot-booking-status="{{ $booking?->status }}"
                                    data-lot-action-url="{{ $managementRole === 'admin' && $lot ? route('admin.properties.blocks.lots.edit', [$property, $lot->block, $lot]) : ($managementRole === 'pembangunan' && $lot ? route('pembangunan.properties.blocks.lots.edit', [$property, $lot->block, $lot]) : ($managementRole === 'marketing' && $lot ? ($editableBooking ? route('marketing.bookings.edit', $editableBooking) : ($lot->status === 'available' ? route('marketing.bookings.create', ['property_id' => $property->id, 'lot_id' => $lot->id]) : '')) : '')) }}"
                                    data-lot-action-label="{{ in_array($managementRole, ['admin', 'pembangunan'], true) ? 'Edit kavling' : ($booking ? 'Edit booking' : 'Tambah booking') }}"
                                    points="{{ $points }}"
                                    fill="{{ $status['fill'] }}"
                                    fill-opacity="0.42"
                                    stroke="{{ $status['color'] }}"
                                    stroke-width="0.28"
                                    vector-effect="non-scaling-stroke"
                                    tabindex="0"
                                    role="button"
                                    aria-label="{{ $lot ? 'Kavling '.$lotLabel.', '.$status['label'] : $lotLabel }}"
                                    aria-pressed="{{ (string) $selectedLotId === (string) $lot?->id && $lot ? 'true' : 'false' }}"
                                    class="cursor-pointer transition hover:fill-opacity-70 focus:outline-none"
                                />
                            @endforeach
                            @foreach($legacyMappedLots as $lot)
                                @php
                                    $coordinates = $lot->map_coordinates;
                                    $status = $lotStatuses[$lot->status] ?? ['label' => ucfirst($lot->status), 'color' => '#64748b', 'fill' => '#94a3b8'];
                                    $label = $lot->block->name.'/'.$lot->lot_number;
                                    $booking = $lot->bookings->first();
                                    $editableBooking = $booking && $booking->status !== 'finish' ? $booking : null;
                                    $legacyPoints = [
                                        ['x' => $coordinates['x'], 'y' => $coordinates['y']],
                                        ['x' => $coordinates['x'] + $coordinates['width'], 'y' => $coordinates['y']],
                                        ['x' => $coordinates['x'] + $coordinates['width'], 'y' => $coordinates['y'] + $coordinates['height']],
                                        ['x' => $coordinates['x'], 'y' => $coordinates['y'] + $coordinates['height']],
                                    ];
                                    $points = implode(' ', array_map(
                                        fn (array $point): string => number_format($point['x'], 4, '.', '').','.number_format($point['y'], 4, '.', ''),
                                        $legacyPoints,
                                    ));
                                @endphp
                                <polygon
                                    data-site-plan-lot
                                    data-lot-id="{{ $lot->id }}"
                                    data-lot-mapped="true"
                                    data-lot-label="{{ $label }}"
                                    data-lot-status="{{ $status['label'] }}"
                                    data-lot-color="{{ $status['color'] }}"
                                    data-lot-price="{{ $lot->house_price }}"
                                    data-lot-notes="{{ $lot->notes }}"
                                    data-lot-images="{{ json_encode(array_column($lot->imageGallery(), 'url')) }}"
                                    data-lot-property="{{ $property->name }}"
                                    data-lot-block="{{ $lot->block->name }}"
                                    data-lot-number="{{ $lot->lot_number }}"
                                    data-lot-customer="{{ $booking?->customer?->name }}"
                                    data-lot-sales="{{ $booking?->sales?->name }}"
                                    data-lot-booking-status="{{ $booking?->status }}"
                                    data-lot-action-url="{{ $managementRole === 'admin' ? route('admin.properties.blocks.lots.edit', [$property, $lot->block, $lot]) : ($managementRole === 'pembangunan' ? route('pembangunan.properties.blocks.lots.edit', [$property, $lot->block, $lot]) : ($managementRole === 'marketing' && $lot ? ($editableBooking ? route('marketing.bookings.edit', $editableBooking) : ($lot->status === 'available' ? route('marketing.bookings.create', ['property_id' => $property->id, 'lot_id' => $lot->id]) : '')) : '')) }}"
                                    data-lot-action-label="{{ in_array($managementRole, ['admin', 'pembangunan'], true) ? 'Edit kavling' : ($booking ? 'Edit booking' : 'Tambah booking') }}"
                                    points="{{ $points }}"
                                    fill="{{ $status['fill'] }}"
                                    fill-opacity="0.42"
                                    stroke="{{ $status['color'] }}"
                                    stroke-width="0.28"
                                    vector-effect="non-scaling-stroke"
                                    tabindex="0"
                                    role="button"
                                    aria-label="Kavling {{ $label }}, {{ $status['label'] }}"
                                    aria-pressed="{{ (string) $selectedLotId === (string) $lot->id ? 'true' : 'false' }}"
                                    class="cursor-pointer transition hover:fill-opacity-70 focus:outline-none"
                                />
                            @endforeach
                        </svg>
                    </div>
                </div>
            </div>

            <aside @class([
                'space-y-4 xl:flex xl:min-h-full xl:flex-col xl:justify-between xl:space-y-0' => ! $wide,
                'space-y-4',
                'flex flex-wrap items-start gap-4' => $wide,
            ])>
                <div @class([
                    'flex flex-col gap-3',
                    'xl:flex-1 xl:justify-evenly' => ! $wide,
                ]) aria-label="Keterangan warna status kavling">
                    @foreach($lotStatuses as $status)
                        <span @class([
                            'inline-flex items-center gap-2 text-sm font-medium text-slate-800 dark:text-zinc-200',
                            'xl:min-h-12 xl:rounded-lg xl:border xl:border-slate-200 xl:bg-slate-50 xl:px-3 xl:py-2 dark:xl:border-zinc-700 dark:xl:bg-zinc-800' => ! $wide,
                        ])>
                            <span class="size-4 shrink-0 rounded-full border-2 shadow-sm" style="background-color: {{ $status['color'] }}; border-color: {{ $status['color'] }}"></span>
                            {{ $status['label'] }}
                        </span>
                    @endforeach
                </div>

                <p data-site-plan-empty class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500">
                    Pilih area kavling pada denah untuk melihat detailnya.
                </p>

                @if($detailMode === 'modal')
                    <dialog data-site-plan-modal aria-label="Detail kavling" class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-xl border-0 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/60 dark:bg-zinc-900 dark:text-white">
                @endif
                <div data-site-plan-detail @if($detailMode === 'panel') hidden @endif class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Detail kavling terpilih</p>
                            <h3 data-site-plan-lot-label class="mt-1 text-xl font-bold text-[#10233D] dark:text-white"></h3>
                        </div>
                        @if($detailMode === 'modal')
                            <button type="button" data-site-plan-modal-close class="rounded-md px-2 py-1 text-sm font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-zinc-800 dark:hover:text-white">Tutup</button>
                        @endif
                    </div>
                    <span data-site-plan-lot-status class="mt-2 inline-flex rounded-full px-3 py-1.5 text-sm font-semibold"></span>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-xs text-slate-500">Perumahan</dt><dd data-site-plan-lot-property class="font-medium"></dd></div>
                        <div><dt class="text-xs text-slate-500">Blok / Kavling</dt><dd data-site-plan-lot-block class="font-medium"></dd></div>
                        <div data-site-plan-customer-row hidden><dt class="text-xs text-slate-500">Konsumen</dt><dd data-site-plan-lot-customer class="font-medium"></dd></div>
                        <div data-site-plan-sales-row hidden><dt class="text-xs text-slate-500">Sales</dt><dd data-site-plan-lot-sales class="font-medium"></dd></div>
                    </dl>
                    <p data-site-plan-unmapped hidden class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">Area ini belum dihubungkan dengan record kavling.</p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Foto Kavling</p>
                            <div data-site-plan-lot-images hidden class="grid grid-cols-2 gap-2"></div>
                            <p data-site-plan-lot-image-empty class="flex aspect-4/3 items-center justify-center rounded-lg bg-slate-100 px-2 text-center text-xs text-slate-500 dark:bg-zinc-800">Foto belum tersedia</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Denah Perumahan</p>
                            <img src="{{ $property->sitePlanUrl() }}" alt="Denah {{ $property->name }}" class="aspect-4/3 w-full rounded-lg bg-slate-100 object-contain dark:bg-zinc-800">
                        </div>
                    </div>

                    <div data-site-plan-price-section class="mt-4 rounded-lg bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Harga</p>
                        <p data-site-plan-lot-price class="mt-1 text-lg font-bold text-[#10233D]"></p>
                    </div>

                    <div data-site-plan-notes-section class="mt-4" hidden>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</p>
                        <p data-site-plan-lot-notes class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700"></p>
                    </div>
                    <a data-site-plan-lot-action hidden class="mt-4 inline-flex rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800"></a>
                </div>
                @if($detailMode === 'modal')
                    </dialog>
                @endif

                <p class="text-xs text-slate-400">
                    {{ $mappedLotCount }} dari {{ $lots->count() }} kavling sudah ditandai.
                </p>
            </aside>
        </div>
    @endif
</section>
