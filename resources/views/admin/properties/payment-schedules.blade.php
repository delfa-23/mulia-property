<x-layouts::app :title="'Jadwal Pembayaran Booking'">
    <div class="mx-auto max-w-4xl space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('admin.properties.edit', $property) }}" class="text-sm font-medium text-blue-700 hover:underline">Kembali ke perumahan</a>
                <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">Jadwal Pembayaran Booking</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">{{ $property->name }} · Nominal pembayaran ditentukan admin dan berlaku untuk booking di perumahan ini.</p>
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
                <h2 class="font-semibold text-gray-900 dark:text-white">Daftar tahap aktif</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">Urutan tahap mengikuti daftar ini. Total nominal aktif tidak boleh melebihi harga kavling terendah.</p>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($schedules as $schedule)
                    <article class="grid gap-3 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $schedule->title }}</p>
                            <p class="text-sm text-gray-600 dark:text-zinc-400">Rp {{ number_format((float) $schedule->amount, 0, ',', '.') }} · {{ $schedule->is_active ? 'Aktif' : 'Nonaktif' }}</p>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('admin.properties.payment-schedules.update', [$property, $schedule]) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="title" value="{{ $schedule->title }}">
                                <input type="hidden" name="amount" value="{{ (int) $schedule->amount }}">
                                <input type="hidden" name="is_active" value="{{ $schedule->is_active ? 0 : 1 }}">
                                <button type="submit" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                    {{ $schedule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.properties.payment-schedules.destroy', [$property, $schedule]) }}" onsubmit="return confirm('Hapus atau nonaktifkan jadwal pembayaran ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950">Hapus</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="py-5 text-sm text-zinc-500">Belum ada jadwal pembayaran untuk perumahan ini.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="font-semibold text-gray-900 dark:text-white">Tambah jadwal pembayaran</h2>
            <form method="POST" action="{{ route('admin.properties.payment-schedules.store', $property) }}" class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(12rem,0.7fr)_auto] sm:items-end">
                @csrf
                <div>
                    <label for="schedule-title" class="mb-1 block text-sm font-medium text-gray-700 dark:text-zinc-300">Judul pembayaran</label>
                    <input id="schedule-title" name="title" value="{{ old('title') }}" maxlength="100" required placeholder="Contoh: Pembayaran 1" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                    @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="schedule-amount" class="mb-1 block text-sm font-medium text-gray-700 dark:text-zinc-300">Nominal (Rp)</label>
                    <input id="schedule-amount" name="amount" value="{{ old('amount') }}" inputmode="numeric" data-currency-input data-currency-min="1" required placeholder="1.000.000" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                    @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Tambah jadwal</button>
            </form>
        </section>
    </div>
</x-layouts::app>
