<x-layouts::app :title="'Jadwal Pembayaran Booking'">
    <div class="mx-auto max-w-4xl space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('admin.properties.edit', $property) }}" class="text-sm font-medium text-blue-700 hover:underline">Kembali ke perumahan</a>
                <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">Jadwal Pembayaran Booking</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">{{ $property->name }} · Jadwal pembayaran dibuat untuk satu booking. Yang berlaku lintas booking hanya konfigurasi Cash/KPR.</p>
                <a href="{{ route('admin.payment-settings.index') }}" class="mt-3 inline-flex rounded-lg border border-blue-300 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-950/30">Konfigurasi Umum Cash / KPR</a>
            </div>
        </header>

        @if(session('success'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/30 dark:text-green-200">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
                <ul class="list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <h2 class="font-semibold text-gray-900 dark:text-white">Jadwal lama yang belum ditetapkan ke booking</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">Pilih booking pemilik untuk memindahkan jadwal lama. Transaksi yang sudah tercatat harus tetap sesuai dengan booking yang dipilih.</p>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($schedules as $schedule)
                    <article class="grid gap-3 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $schedule->title }}</p>
                            <p class="text-sm text-gray-600 dark:text-zinc-400">Rp {{ number_format((float) $schedule->amount, 0, ',', '.') }} · {{ $schedule->is_active ? 'Aktif' : 'Nonaktif' }}</p>
                        </div>
                        @if($schedule->has_multiple_transaction_bookings)
                            <p class="max-w-md text-sm font-semibold text-amber-900 dark:text-amber-200">Jadwal ini memiliki transaksi dari beberapa booking. Periksa dan selesaikan pemisahan transaksi sebelum menetapkan jadwal.</p>
                        @else
                            <form method="POST" action="{{ route('admin.properties.payment-schedules.assign', [$property, $schedule]) }}" class="grid gap-2 sm:min-w-72">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="legacy_schedule_id" value="{{ $schedule->id }}">
                                <label for="schedule-owner-{{ $schedule->id }}" class="text-sm font-medium text-gray-700 dark:text-zinc-300">Booking pemilik jadwal</label>
                                <select id="schedule-owner-{{ $schedule->id }}" name="booking_id" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                                    <option value="">Pilih booking</option>
                                    @foreach($allBookings as $bookingOption)
                                        <option value="{{ $bookingOption->id }}" @selected(
                                            (old('legacy_schedule_id') == $schedule->id ? old('booking_id') : $schedule->transaction_booking_id) == $bookingOption->id
                                        )>
                                            Booking #{{ $bookingOption->id }} · {{ $bookingOption->customer->name }} · {{ $bookingOption->lot->block->name }}-{{ $bookingOption->lot->lot_number }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Tetapkan ke booking ini</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="py-5 text-sm text-zinc-500">Tidak ada jadwal lama yang perlu ditetapkan.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="font-semibold text-gray-900 dark:text-white">Tambah jadwal untuk booking tertentu</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">Booking Fee dan DP diambil dari konfigurasi Cash/KPR. Tahap pembayaran lainnya hanya berlaku untuk booking yang dipilih.</p>
            <form method="POST" action="{{ route('admin.properties.payment-schedules.store', $property) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                <div>
                    <label for="schedule-title" class="mb-1 block text-sm font-medium text-gray-700 dark:text-zinc-300">Judul pembayaran</label>
                    <input id="schedule-title" name="title" value="{{ old('title') }}" maxlength="100" required placeholder="Contoh: Pembayaran 1" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                    @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="schedule-booking" class="mb-1 block text-sm font-medium text-gray-700 dark:text-zinc-300">Booking</label>
                    <select id="schedule-booking" name="booking_id" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                        <option value="">Pilih booking</option>
                        @foreach($bookings as $bookingOption)
                            <option value="{{ $bookingOption->id }}" @selected(old('booking_id') == $bookingOption->id)>
                                Booking #{{ $bookingOption->id }} · {{ $bookingOption->customer->name }} · {{ $bookingOption->lot->block->name }}-{{ $bookingOption->lot->lot_number }}
                            </option>
                        @endforeach
                    </select>
                    @error('booking_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="schedule-amount" class="mb-1 block text-sm font-medium text-gray-700 dark:text-zinc-300">Nominal (Rp)</label>
                    <input id="schedule-amount" name="amount" value="{{ old('amount') }}" inputmode="numeric" data-currency-input data-currency-min="1" required placeholder="1.000.000" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                    @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end">
                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Tambah jadwal</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts::app>
