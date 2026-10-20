<x-layouts::app :title="__('Laporan CRM Marketing')">
    <div class="space-y-6">
        <div><h1 class="text-2xl font-semibold">Laporan CRM Marketing</h1><p class="mt-1 text-sm text-zinc-500">Periode tanggal masuk prospek dan tanggal transaksi: {{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }}–{{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }}. Aktivitas berasal dari log CRM, transaksi dari booking terkait prospek.</p></div>
        <form method="GET" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div><label for="report-from" class="mb-1 block text-sm font-medium">Dari tanggal</label><input id="report-from" type="date" name="from" value="{{ $from }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></div>
            <div><label for="report-to" class="mb-1 block text-sm font-medium">Sampai tanggal</label><input id="report-to" type="date" name="to" value="{{ $to }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></div>
            @if($teamMembers->isNotEmpty())
                <div><label for="report-marketing" class="mb-1 block text-sm font-medium">Marketing</label><select id="report-marketing" name="assigned_user_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="">Semua Marketing</option>@foreach($teamMembers as $teamMember)<option value="{{ $teamMember->id }}" @selected(($filters['assigned_user_id'] ?? '') == $teamMember->id)>{{ $teamMember->name }}</option>@endforeach</select></div>
            @endif
            <div><label for="report-property" class="mb-1 block text-sm font-medium">Perumahan</label><select id="report-property" name="property_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="">Semua perumahan</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected(($filters['property_id'] ?? '') == $property->id)>{{ $property->name }}</option>@endforeach</select></div>
            <div><label for="report-category" class="mb-1 block text-sm font-medium">Kategori prospek</label><select id="report-category" name="category" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $categoryLabels[$category] ?? $category }}</option>@endforeach</select></div>
            <div><label for="report-source" class="mb-1 block text-sm font-medium">Sumber</label><select id="report-source" name="source" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="">Semua sumber</option>@foreach($sources as $source)<option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $source }}</option>@endforeach</select></div>
            <div class="flex gap-2 xl:col-span-6"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Terapkan filter</button><a href="{{ route('marketing.reports.crm') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm dark:border-zinc-600" wire:navigate>Reset</a></div>
        </form>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Total prospek masuk', $metrics['leads'], 'Prospek CRM · tanggal masuk'],
                ['Prospek kategori Hot', $metrics['hot'], 'Prospek CRM · kategori'],
                ['Prospek sudah dihubungi', $metrics['contacted'], 'Log panggilan CRM · prospek unik'],
                ['Prospek sudah survei', $metrics['surveyed'], 'Log survei selesai CRM · prospek unik'],
                ['Booking dari prospek', $metrics['bookings'], 'Transaksi terkait prospek · tanggal booking'],
                ['Booking mencapai akad / serah terima', $metrics['akad_bookings'], 'Status booking dari proses Pemberkasan'],
                ['Konversi prospek → booking', $metrics['lead_to_booking_rate'].'%', 'Prospek unik dengan booking pada periode'],
                ['Konversi booking → akad', $metrics['booking_to_akad_rate'].'%', 'Booking aktif periode ini · status dari Pemberkasan'],
            ] as [$label, $value, $source])
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $value }}</p><p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Sumber: {{ $source }}</p></div>
            @endforeach
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold">Prospek berdasarkan sumber</h2>
            <p class="mt-1 text-sm text-zinc-500">Sumber data: kolom sumber pada Prospek CRM.</p>
            <div class="mt-3 space-y-2">@forelse($sourceCounts as $row)<div class="flex justify-between gap-3 border-b border-zinc-100 py-2 text-sm dark:border-zinc-800"><span>{{ $row->source ?: 'Tanpa sumber' }}</span><span class="font-semibold">{{ number_format($row->total) }}</span></div>@empty<p class="text-sm text-zinc-500">Tidak ada data pada periode ini.</p>@endforelse</div>
        </section>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="p-5"><h2 class="text-lg font-semibold">Ringkasan Marketing</h2><p class="mt-1 text-sm text-zinc-500">Prospek, kontak, dan survei berasal dari Prospek CRM berdasarkan PIC CRM; booking berasal dari transaksi terkait prospek, sedangkan status akad dan serah terima mengikuti proses Pemberkasan.</p></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800"><tr><th class="px-4 py-3">Marketing (PIC CRM)</th><th class="px-4 py-3">Prospek</th><th class="px-4 py-3">Dihubungi</th><th class="px-4 py-3">Survei</th><th class="px-4 py-3">Booking</th><th class="px-4 py-3">Booking akad / serah terima</th></tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">@forelse($performance as $row)<tr><td class="px-4 py-3 font-medium">{{ $row['user']->name }}</td><td class="px-4 py-3">{{ $row['leads'] }}</td><td class="px-4 py-3">{{ $row['contacted'] }}</td><td class="px-4 py-3">{{ $row['surveyed'] }}</td><td class="px-4 py-3">{{ $row['bookings'] }}</td><td class="px-4 py-3">{{ $row['akad'] }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-6 text-center text-zinc-500">Tidak ada data Marketing dengan prospek pada periode ini.</td></tr>@endforelse</tbody>
            </table></div>
        </section>
    </div>
</x-layouts::app>
