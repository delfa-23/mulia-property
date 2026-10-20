<x-layouts::app :title="__('Dashboard Pemberkasan')">
	<div class="space-y-6">
		<div>
			<h1 class="text-2xl font-semibold">Dashboard Pemberkasan</h1>
			<p class="mt-1 text-sm text-zinc-500">Pantau kelengkapan berkas sampai jadwal akad.</p>
		</div>

		<form method="GET" action="{{ route('pemberkasan.dashboard') }}" class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3">
			<div>
				<label for="property_id" class="mb-1 block text-sm font-medium">Perumahan</label>
				<select id="property_id" name="property_id" data-property-select class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2">
					<option value="">Semua Perumahan</option>
					@foreach($properties as $property)
						<option value="{{ $property->id }}" @selected($propertyId === $property->id)>{{ $property->name }}</option>
					@endforeach
				</select>
			</div>
			<div>
				<label for="lot_id" class="mb-1 block text-sm font-medium">Kavling</label>
				<select id="lot_id" name="lot_id" data-lot-select disabled class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2">
					<option value="">Semua Kavling</option>
					@foreach($lots as $lot)
						<option value="{{ $lot->id }}" data-property-id="{{ $lot->block->property_id }}" @selected($lotId === $lot->id)>{{ $lot->block->name }}/{{ $lot->lot_number }}</option>
					@endforeach
				</select>
			</div>
			<div class="flex items-end gap-2">
				<button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">Terapkan</button>
				@if($propertyId || $lotId)
					<a href="{{ route('pemberkasan.dashboard') }}" class="px-3 py-2 text-sm text-zinc-600">Reset</a>
				@endif
			</div>
		</form>

		<x-site-plan-map
			:property="$selectedProperty"
			:lots="$sitePlanLots"
			:areas="$sitePlanAreas"
			:selected-lot-id="$lotId"
			:manage-url="null"
			:dashboard-url="route('pemberkasan.dashboard')"
			detail-mode="modal"
			:management-role="null"
		/>

		@php
			$dashboardFilters = array_filter([
				'property_id' => $propertyId,
				'block_id' => $blockId,
				'lot_id' => $lotId,
			], fn ($value): bool => $value !== null);
		@endphp
		<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
			@foreach($workflowChart as $workflow)
				<a
					href="{{ route('pemberkasan.dashboard', $dashboardFilters + ['workflow' => $workflow['key']]) }}"
					wire:navigate
					@class([
						'rounded-xl border bg-white p-5 transition hover:border-blue-400 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500',
						'border-blue-500 ring-2 ring-blue-100' => $selectedWorkflow === $workflow['key'],
						'border-zinc-200' => $selectedWorkflow !== $workflow['key'],
					])
					aria-current="{{ $selectedWorkflow === $workflow['key'] ? 'true' : 'false' }}"
				>
					<p class="text-sm text-zinc-500">{{ $workflow['card_label'] }}</p>
					<p class="mt-2 text-2xl font-bold">{{ $workflow['value'] }}</p>
					<p class="mt-2 text-xs font-medium text-blue-700">Lihat booking & kavling</p>
				</a>
			@endforeach
		</div>

		@if($workflowBookings)
			<dialog
				data-workflow-bookings-modal
				data-workflow-bookings-modal-open
				aria-labelledby="workflow-bookings-modal-title"
				class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-3xl overflow-y-auto rounded-xl border-0 bg-white p-0 text-zinc-900 shadow-2xl backdrop:bg-slate-950/60 dark:bg-zinc-900 dark:text-white"
			>
				<div class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
					<div>
						<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Detail kategori</p>
						<h2 id="workflow-bookings-modal-title" class="mt-1 text-lg font-semibold">Booking: {{ $workflowChart->firstWhere('key', $selectedWorkflow)['label'] }}</h2>
						<p class="mt-1 text-sm text-zinc-500">{{ $workflowBookings->total() }} booking ditemukan</p>
					</div>
					<button type="button" data-workflow-bookings-modal-close class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">Tutup</button>
				</div>
				<form method="GET" action="{{ route('pemberkasan.dashboard') }}" class="grid gap-3 border-b border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800 sm:grid-cols-2">
					<input type="hidden" name="workflow" value="{{ $selectedWorkflow }}">
					@foreach($dashboardFilters as $filterName => $filterValue)
						<input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
					@endforeach
					<div>
						<label for="workflow_property_id" class="mb-1 block text-sm font-medium">Perumahan</label>
						<select id="workflow_property_id" name="workflow_property_id" data-property-select class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900">
							<option value="">Semua Perumahan</option>
							@foreach($properties as $property)
								<option value="{{ $property->id }}" @selected($workflowPropertyId === $property->id)>{{ $property->name }}</option>
							@endforeach
						</select>
					</div>
					<div>
						<label for="workflow_lot_id" class="mb-1 block text-sm font-medium">Kavling</label>
						<select id="workflow_lot_id" name="workflow_lot_id" data-lot-select disabled class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900">
							<option value="">Semua Kavling</option>
							@foreach($workflowFilterLots as $filterLot)
								<option value="{{ $filterLot->id }}" data-property-id="{{ $filterLot->block->property_id }}" @selected($workflowLotId === $filterLot->id)>{{ $filterLot->block->property->name }} - {{ $filterLot->block->name }}/{{ $filterLot->lot_number }}</option>
							@endforeach
						</select>
					</div>
					<div class="flex items-end gap-2 sm:col-span-2">
						<button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Terapkan Filter</button>
						@if($workflowPropertyId || $workflowLotId)
							<a href="{{ route('pemberkasan.dashboard', $dashboardFilters + ['workflow' => $selectedWorkflow]) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-200 dark:text-zinc-300 dark:hover:bg-zinc-700">Reset Filter</a>
						@endif
					</div>
				</form>
				<div class="divide-y divide-zinc-200 dark:divide-zinc-700">
					@forelse($workflowBookings as $workflowBooking)
						@php
							$lotStatus = config('lot_statuses.'.$workflowBooking->lot->status.'.label', ucfirst($workflowBooking->lot->status));
						@endphp
						<article class="space-y-3 p-5">
							<div class="flex flex-wrap items-start justify-between gap-3">
								<div>
									<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $workflowBooking->lot->block->property->name }}</p>
									<h3 class="mt-1 text-lg font-bold">{{ $workflowBooking->lot->block->name }}/{{ $workflowBooking->lot->lot_number }}</h3>
								</div>
								<span class="rounded-full bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-800 dark:bg-blue-950/50 dark:text-blue-200">{{ $lotStatus }}</span>
							</div>
							<dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
								<div><dt class="text-xs text-zinc-500">Customer</dt><dd class="font-medium">{{ $workflowBooking->customer->name }}</dd></div>
								<div><dt class="text-xs text-zinc-500">Sales</dt><dd class="font-medium">{{ $workflowBooking->sales?->name ?? '-' }}</dd></div>
								<div><dt class="text-xs text-zinc-500">Status booking</dt><dd class="font-medium">{{ ucfirst($workflowBooking->status) }}</dd></div>
								<div><dt class="text-xs text-zinc-500">Berkas</dt><dd class="font-medium">{{ ucfirst($workflowBooking->documentProcess?->status ?? 'Belum diisi') }}</dd></div>
								<div><dt class="text-xs text-zinc-500">BI Checking</dt><dd class="font-medium">{{ ucfirst(str_replace('_', ' ', $workflowBooking->documentProcess?->bi_checking_status ?? 'not_checked')) }}</dd></div>
								<div><dt class="text-xs text-zinc-500">Proses bank</dt><dd class="font-medium">{{ ucfirst(str_replace('_', ' ', $workflowBooking->bankProcess?->status ?? 'Belum diisi')) }}</dd></div>
								<div><dt class="text-xs text-zinc-500">SP3</dt><dd class="font-medium">{{ $workflowBooking->sp3?->sp3_number ?: ucfirst($workflowBooking->sp3?->status ?? 'Belum diisi') }}</dd></div>
								<div><dt class="text-xs text-zinc-500">Jadwal akad</dt><dd class="font-medium">{{ $workflowBooking->akadSchedule?->scheduled_at?->format('d/m/Y H:i') ?? 'Belum dijadwalkan' }}</dd></div>
								<div><dt class="text-xs text-zinc-500">Harga kavling</dt><dd class="font-medium">Rp {{ number_format((float) $workflowBooking->lot->house_price, 0, ',', '.') }}</dd></div>
							</dl>
							<a href="{{ route('pemberkasan.bookings.show', $workflowBooking) }}" class="inline-flex rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800" wire:navigate>Lihat detail booking</a>
						</article>
					@empty
						<p class="px-5 py-8 text-center text-sm text-zinc-500">Tidak ada booking untuk kategori ini.</p>
					@endforelse
				</div>
				@if($workflowBookings->hasPages())
					<div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">{{ $workflowBookings->links() }}</div>
				@endif
			</dialog>
		@endif

		<div class="grid gap-5 xl:grid-cols-3">
			<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
				<h2 class="text-lg font-bold text-[#10233D]">Tren Booking 6 Bulan</h2>
				<p class="mt-1 text-sm text-slate-500">Booking aktif yang memerlukan proses pemberkasan per bulan.</p>
				@if($monthlyBookingChart->sum('bookings') > 0)
					<div role="img" aria-label="Grafik tren booking pemberkasan enam bulan" class="mt-5 grid h-52 grid-cols-6 items-end gap-2 border-b border-slate-200 px-1 sm:gap-4">
						@foreach($monthlyBookingChart as $month)
							@php($barHeight = $month['bookings'] > 0 ? max(4, ($month['bookings'] / $monthlyBookingChartMax) * 100) : 0)
							<div class="flex h-full min-w-0 flex-col items-center justify-end gap-2">
								<span class="text-xs font-bold text-[#10233D]">{{ $month['bookings'] }}</span>
								<div class="flex h-36 w-full items-end justify-center"><div class="w-1/2 rounded-t-md bg-[#EB5120]" style="height: {{ $barHeight }}%" title="{{ $month['label'] }}: {{ $month['bookings'] }} booking"></div></div>
								<span class="pb-2 text-center text-[10px] font-semibold text-slate-500 sm:text-xs">{{ $month['label'] }}</span>
							</div>
						@endforeach
					</div>
				@else
					<p class="mt-5 flex h-52 items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-500">Belum ada booking aktif dalam enam bulan terakhir.</p>
				@endif
			</section>

			<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
				<h2 class="text-lg font-bold text-[#10233D]">Pipeline Pemberkasan</h2>
				<p class="mt-1 text-sm text-slate-500">Volume pada setiap tahapan proses.</p>
				<div class="mt-5 space-y-3">
					@foreach($workflowChart as $workflow)
						@php($barWidth = $workflow['value'] > 0 ? max(5, ($workflow['value'] / $workflowChartMax) * 100) : 0)
						<div>
							<div class="mb-1 flex justify-between gap-3 text-xs"><span class="min-w-0 truncate font-semibold text-slate-600">{{ $workflow['label'] }}</span><span class="shrink-0 font-bold text-[#10233D]">{{ $workflow['value'] }}</span></div>
							<div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full" style="width: {{ $barWidth }}%; background-color: {{ $workflow['color'] }}"></div></div>
						</div>
					@endforeach
				</div>
			</section>
		</div>

		<section class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
			<div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
				<h2 class="text-lg font-semibold">Booking yang Perlu Diproses</h2>
				<a href="{{ route('pemberkasan.bookings.index') }}" class="text-sm text-blue-600" wire:navigate>Lihat semua</a>
			</div>
			<div class="overflow-x-auto">
				<table class="w-full text-left text-sm">
					<thead class="bg-zinc-50">
						<tr>
							<th class="px-6 py-3">Customer</th>
							<th class="px-6 py-3">Kavling</th>
							<th class="px-6 py-3">Berkas</th>
							<th class="px-6 py-3">BI</th>
							<th class="px-6 py-3">Bank</th>
							<th class="px-6 py-3">Aksi</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-zinc-200">
						@forelse($bookings as $booking)
							<tr>
								<td class="px-6 py-4">{{ $booking->customer->name }}</td>
								<td class="px-6 py-4">{{ $booking->lot->block->name }}/{{ $booking->lot->lot_number }}</td>
								<td class="px-6 py-4">{{ $booking->documentProcess?->status ?? 'Belum diisi' }}</td>
								<td class="px-6 py-4">{{ $booking->documentProcess?->bi_checking_status ?? '-' }}</td>
								<td class="px-6 py-4">{{ $booking->bankProcess?->status ?? 'Belum diisi' }}</td>
								<td class="px-6 py-4"><a href="{{ route('pemberkasan.bookings.show', $booking) }}" class="text-blue-600" wire:navigate>Kelola</a></td>
							</tr>
						@empty
							<tr><td colspan="6" class="px-6 py-10 text-center text-zinc-500">Belum ada booking.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</section>
	</div>
</x-layouts::app>
