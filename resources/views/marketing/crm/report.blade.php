<x-layouts::app :title="__('Laporan CRM Marketing')">
    <div class="space-y-6">
        <div><h1 class="text-2xl font-semibold">Laporan CRM Marketing</h1><p class="mt-1 text-sm text-zinc-500">Ringkasan performa prospek, booking, akad, dan follow-up untuk {{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }}–{{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }}.</p></div>
        <form method="GET" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div><label for="report-from" class="mb-1 block text-sm font-medium">Dari tanggal</label><input id="report-from" type="date" name="from" value="{{ $from }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></div>
            <div><label for="report-to" class="mb-1 block text-sm font-medium">Sampai tanggal</label><input id="report-to" type="date" name="to" value="{{ $to }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"></div>
            @if($teamMembers->isNotEmpty())
                <div><label for="report-marketing" class="mb-1 block text-sm font-medium">Marketing</label><select id="report-marketing" name="assigned_user_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="">Semua Marketing</option>@foreach($teamMembers as $teamMember)<option value="{{ $teamMember->id }}" @selected(($filters['assigned_user_id'] ?? '') == $teamMember->id)>{{ $teamMember->name }}</option>@endforeach</select></div>
            @endif
            <div><label for="report-property" class="mb-1 block text-sm font-medium">Perumahan</label><select id="report-property" name="property_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="">Semua perumahan</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected(($filters['property_id'] ?? '') == $property->id)>{{ $property->name }}</option>@endforeach</select></div>
            <div><label for="report-category" class="mb-1 block text-sm font-medium">Kategori</label><select id="report-category" name="category" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $categoryLabels[$category] ?? $category }}</option>@endforeach</select></div>
            <div><label for="report-source" class="mb-1 block text-sm font-medium">Sumber</label><select id="report-source" name="source" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"><option value="">Semua sumber</option>@foreach($sources as $source)<option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $source }}</option>@endforeach</select></div>
            <div class="flex gap-2 xl:col-span-6"><button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Terapkan filter</button><a href="{{ route('marketing.reports.crm') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm dark:border-zinc-600" wire:navigate>Reset</a></div>
        </form>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Total prospek masuk', $metrics['leads']],
                ['Prospek kategori Hot', $metrics['hot']],
                ['Prospek sudah dihubungi', $metrics['contacted']],
                ['Prospek sudah survei', $metrics['surveyed']],
                ['Booking dari prospek', $metrics['bookings']],
                ['Booking akad / selesai', $metrics['akad_bookings']],
                ['Konversi prospek → booking', $metrics['lead_to_booking_rate'].'%'],
                ['Konversi prospek → akad', $metrics['lead_to_akad_rate'].'%'],
                ['Follow-up selesai', $metrics['follow_ups_completed']],
                ['Follow-up terlambat', $metrics['follow_ups_overdue']],
            ] as [$label, $value])
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"><p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $value }}</p></div>
            @endforeach
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold">Rumus metrik</h2>
            <ul class="mt-3 grid gap-2 text-sm text-zinc-600 sm:grid-cols-2 dark:text-zinc-300">
                <li>Prospek = jumlah prospek aktif berdasarkan tanggal masuk dan filter.</li>
                <li>Sudah dihubungi / survei = jumlah prospek unik dengan aktivitas pada periode.</li>
                <li>Booking = jumlah transaksi booking terkait prospek dengan tanggal booking pada periode.</li>
                <li>Booking akad = booking berstatus <span class="font-mono">akad</span> atau <span class="font-mono">finish</span>.</li>
                <li>Konversi booking = prospek unik yang memiliki booking ÷ total prospek × 100.</li>
                <li>Konversi akad = prospek unik yang memiliki booking akad/selesai ÷ total prospek × 100.</li>
                <li>Follow-up selesai = tugas berstatus selesai dengan pembaruan dalam periode.</li>
                <li>Follow-up terlambat = tugas aktif yang tenggatnya lewat dalam periode hingga saat ini.</li>
            </ul>
            <p class="mt-3 text-xs text-zinc-500">Setiap prospek hanya dihitung satu kali pada metrik kontak, survei, dan konversi; jumlah booking menghitung transaksi, bukan prospek unik.</p>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">Prospek berdasarkan kategori</h2>
                <div class="mt-3 space-y-2">@forelse($categoryCounts as $row)<div class="flex justify-between gap-3 border-b border-zinc-100 py-2 text-sm dark:border-zinc-800"><span>{{ $categoryLabels[$row->category] ?? ($row->category ?: 'Tanpa kategori') }}</span><span class="font-semibold">{{ number_format($row->total) }}</span></div>@empty<p class="text-sm text-zinc-500">Tidak ada data pada periode ini.</p>@endforelse</div>
            </section>
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">Prospek berdasarkan sumber</h2>
                <div class="mt-3 space-y-2">@forelse($sourceCounts as $row)<div class="flex justify-between gap-3 border-b border-zinc-100 py-2 text-sm dark:border-zinc-800"><span>{{ $row->source ?: 'Tanpa sumber' }}</span><span class="font-semibold">{{ number_format($row->total) }}</span></div>@empty<p class="text-sm text-zinc-500">Tidak ada data pada periode ini.</p>@endforelse</div>
            </section>
        </div>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="p-5"><h2 class="text-lg font-semibold">Performa Marketing</h2><p class="mt-1 text-sm text-zinc-500">Tugas dihitung berdasarkan PIC tugas; prospek, kontak, survei, dan booking berdasarkan PIC CRM prospek.</p></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800"><tr><th class="px-4 py-3">Marketing</th><th class="px-4 py-3">Prospek</th><th class="px-4 py-3">Dihubungi</th><th class="px-4 py-3">Survei</th><th class="px-4 py-3">Booking</th><th class="px-4 py-3">Akad</th><th class="px-4 py-3">Follow-up selesai</th><th class="px-4 py-3">Terlambat</th></tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">@forelse($performance as $row)<tr><td class="px-4 py-3 font-medium">{{ $row['user']->name }}</td><td class="px-4 py-3">{{ $row['leads'] }}</td><td class="px-4 py-3">{{ $row['contacted'] }}</td><td class="px-4 py-3">{{ $row['surveyed'] }}</td><td class="px-4 py-3">{{ $row['bookings'] }}</td><td class="px-4 py-3">{{ $row['akad'] }}</td><td class="px-4 py-3">{{ $row['follow_ups_completed'] }}</td><td class="px-4 py-3">{{ $row['follow_ups_overdue'] }}</td></tr>@empty<tr><td colspan="8" class="px-4 py-6 text-center text-zinc-500">Tidak ada data Marketing dengan prospek pada periode ini.</td></tr>@endforelse</tbody>
            </table></div>
        </section>
    </div>
</x-layouts::app>
