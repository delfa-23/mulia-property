@props([
    'property' => null,
    'lots' => collect(),
    'selectedLotId' => null,
    'manageUrl' => null,
    'dashboardUrl',
])

@php
    $lotStatuses = config('lot_statuses');
    $mappedLots = $lots->filter(fn ($lot): bool => is_array($lot->map_coordinates))->values();
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
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]" data-site-plan-viewer data-property-id="{{ $property->id }}" data-dashboard-url="{{ $dashboardUrl }}">
            <div class="min-w-0 overflow-auto rounded-lg border border-slate-200 bg-slate-50 p-2">
                <div class="relative min-w-[640px]">
                    <img
                        src="{{ $property->sitePlanUrl() }}"
                        alt="Denah {{ $property->name }}"
                        class="block h-auto w-full select-none"
                        draggable="false"
                    >
                    @foreach($mappedLots as $lot)
                        @php
                            $coordinates = $lot->map_coordinates;
                            $status = $lotStatuses[$lot->status] ?? ['label' => ucfirst($lot->status), 'color' => '#64748b'];
                            $label = $lot->block->name.'/'.$lot->lot_number;
                        @endphp
                        <button
                            type="button"
                            data-site-plan-lot
                            data-lot-id="{{ $lot->id }}"
                            data-lot-label="{{ $label }}"
                            data-lot-status="{{ $status['label'] }}"
                            data-lot-color="{{ $status['color'] }}"
                            data-lot-price="{{ $lot->house_price }}"
                            data-lot-notes="{{ $lot->notes }}"
                            aria-label="Kavling {{ $label }}, {{ $status['label'] }}"
                            aria-pressed="{{ (string) $selectedLotId === (string) $lot->id ? 'true' : 'false' }}"
                            class="absolute z-10 flex cursor-pointer items-center justify-center overflow-hidden border-2 p-0.5 text-[9px] font-bold leading-none text-slate-950 transition hover:z-20 hover:brightness-95 focus:z-20 focus:outline-2 focus:outline-offset-1 focus:outline-[#10233D]"
                            style="left: {{ $coordinates['x'] }}%; top: {{ $coordinates['y'] }}%; width: {{ $coordinates['width'] }}%; height: {{ $coordinates['height'] }}%; background-color: {{ $status['color'] }}38; border-color: {{ $status['color'] }};"
                        >
                            <span class="overflow-hidden text-ellipsis whitespace-nowrap">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <aside class="space-y-4">
                <div class="flex flex-wrap gap-x-3 gap-y-2" aria-label="Keterangan warna status kavling">
                    @foreach($lotStatuses as $status)
                        <span class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                            <span class="size-2.5 rounded-sm" style="background-color: {{ $status['color'] }}"></span>
                            {{ $status['label'] }}
                        </span>
                    @endforeach
                </div>

                <p data-site-plan-empty class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500">
                    Pilih area kavling pada denah untuk melihat detailnya.
                </p>

                <div data-site-plan-detail hidden class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Detail kavling terpilih</p>
                    <h3 data-site-plan-lot-label class="mt-1 text-xl font-bold text-[#10233D]"></h3>
                    <span data-site-plan-lot-status class="mt-2 inline-flex rounded-full px-3 py-1.5 text-sm font-semibold"></span>

                    <div class="mt-4 rounded-lg bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Harga</p>
                        <p data-site-plan-lot-price class="mt-1 text-lg font-bold text-[#10233D]"></p>
                    </div>

                    <div data-site-plan-notes-section class="mt-4" hidden>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</p>
                        <p data-site-plan-lot-notes class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700"></p>
                    </div>
                </div>

                <p class="text-xs text-slate-400">
                    {{ $mappedLots->count() }} dari {{ $lots->count() }} kavling sudah ditandai.
                </p>
            </aside>
        </div>
    @endif
</section>
