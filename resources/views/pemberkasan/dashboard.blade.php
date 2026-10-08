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

		<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
			@foreach([['Total Booking',$stats['total_bookings']],['Berkas Belum Lengkap',$stats['incomplete_documents']],['Berkas Lengkap',$stats['complete_documents']],['BI Checking',$stats['bi_checking']],['Proses Bank',$stats['bank_processing']],['Bank Disetujui',$stats['bank_approved']],['SP3 Terbit',$stats['sp3_issued']],['Menunggu Akad',$stats['waiting_akad']],['Finish',$stats['finish']],['Revisi',$stats['revision']]] as [$label,$value])
				<div class="rounded-xl border border-zinc-200 bg-white p-5">
					<p class="text-sm text-zinc-500">{{ $label }}</p>
					<p class="mt-2 text-2xl font-bold">{{ $value }}</p>
				</div>
			@endforeach
		</div>

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
