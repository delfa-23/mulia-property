<x-layouts::app :title="__('Activity Logs')">
	<div class="space-y-6">
		<div>
			<h1 class="text-2xl font-semibold">Activity Logs</h1>
			<p class="mt-1 text-sm text-zinc-500">Audit aktivitas penting pengguna dan modul.</p>
		</div>

		<form
			method="GET"
			action="{{ route('admin.activity-logs.index') }}"
			class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"
		>
			<div class="grid gap-4 md:grid-cols-4">
				<div>
					<label for="date_from" class="mb-2 block text-sm font-medium">Dari tanggal</label>
					<input
						id="date_from"
						type="date"
						name="date_from"
						value="{{ request('date_from') }}"
						class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
					>
				</div>

				<div>
					<label for="date_to" class="mb-2 block text-sm font-medium">Sampai tanggal</label>
					<input
						id="date_to"
						type="date"
						name="date_to"
						value="{{ request('date_to') }}"
						class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
					>
				</div>

				<div>
					<label for="action" class="mb-2 block text-sm font-medium">Jenis aktivitas</label>
					<select
						id="action"
						name="action"
						class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
					>
						<option value="">Semua aktivitas</option>
						@foreach($actions as $action)
							<option value="{{ $action }}" @selected(request('action') === $action)>
								{{ ucwords(str_replace(['.', '_'], [' - ', ' '], $action)) }}
							</option>
						@endforeach
					</select>
				</div>

				<div class="flex items-end gap-2">
					<flux:button type="submit" variant="primary">Filter</flux:button>
					<flux:button
						type="button"
						variant="ghost"
						:href="route('admin.activity-logs.index')"
						wire:navigate
					>
						Reset
					</flux:button>
				</div>
			</div>

			@error('date_from')
				<p class="mt-2 text-sm text-red-600">{{ $message }}</p>
			@enderror
			@error('date_to')
				<p class="mt-2 text-sm text-red-600">{{ $message }}</p>
			@enderror
		</form>

		<div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
			<div class="overflow-x-auto">
				<table class="w-full text-left text-sm">
					<thead class="bg-zinc-50 dark:bg-zinc-800">
						<tr>
							<th class="px-6 py-3">Waktu</th>
							<th class="px-6 py-3">User</th>
							<th class="px-6 py-3">Action</th>
							<th class="px-6 py-3">Description</th>
							<th class="px-6 py-3">IP</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
						@forelse($logs as $log)
							<tr>
								<td class="px-6 py-4">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
								<td class="px-6 py-4">{{ $log->user?->name ?? '-' }}</td>
								<td class="px-6 py-4">{{ $log->action }}</td>
								<td class="px-6 py-4">{{ $log->description }}</td>
								<td class="px-6 py-4">{{ $log->ip_address ?? '-' }}</td>
							</tr>
						@empty
							<tr>
								<td colspan="5" class="px-6 py-10 text-center text-zinc-500">Belum ada aktivitas.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
			<div class="px-6 py-4">{{ $logs->links() }}</div>
		</div>
	</div>
</x-layouts::app>
