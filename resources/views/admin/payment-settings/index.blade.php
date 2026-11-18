<x-layouts::app :title="'Konfigurasi Pembayaran Booking'">
    <div class="mx-auto max-w-5xl space-y-6">
        <header>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Konfigurasi Pembayaran Booking</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">Nominal dan deadline berlaku untuk semua booking sesuai cara pembayaran. Jadwal pembayaran perumahan tetap menjadi tahap lanjutan untuk sisa harga.</p>
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

        @php($selectedCategory = old('payment_category', 'cash'))
        <div x-data="{ paymentCategory: @js($selectedCategory) }" class="space-y-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <label for="payment-category" class="mb-2 block text-sm font-medium text-gray-700 dark:text-zinc-300">Cara Pembayaran</label>
                <select id="payment-category" x-model="paymentCategory" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                    <option value="cash">Cash</option>
                    <option value="kpr">KPR</option>
                </select>
                <p class="mt-2 text-sm text-gray-600 dark:text-zinc-400">Pilih Cash atau KPR untuk menampilkan nominal dan deadline yang akan dikonfigurasi.</p>
            </div>

            @foreach(['cash' => 'Cash', 'kpr' => 'KPR'] as $category => $label)
                @php($setting = $settings[$category])
                <section x-show="paymentCategory === '{{ $category }}'" x-cloak class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Konfigurasi {{ $label }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">Nominal akan diterapkan ke seluruh booking {{ $label }}. Deadline booking aktif dihitung ulang setelah konfigurasi disimpan.</p>
                    </div>

                    <form method="POST" action="{{ route('admin.payment-settings.update', $category) }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="payment_category" value="{{ $category }}">

                        <div>
                            <label for="{{ $category }}-booking-fee" class="mb-1 block text-sm font-medium text-gray-700 dark:text-zinc-300">Booking Fee (Rp)</label>
                            <input id="{{ $category }}-booking-fee" name="booking_fee_amount" value="{{ old('payment_category') === $category ? old('booking_fee_amount', (int) $setting->booking_fee_amount) : (int) $setting->booking_fee_amount }}" inputmode="numeric" data-currency-input data-currency-min="0" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                        </div>

                        <div>
                            <label for="{{ $category }}-booking-fee-deadline" class="mb-1 block text-sm font-medium text-gray-700 dark:text-zinc-300">Deadline Booking Fee (jam)</label>
                            <input id="{{ $category }}-booking-fee-deadline" type="number" name="booking_fee_deadline_hours" value="{{ old('payment_category') === $category ? old('booking_fee_deadline_hours', $setting->booking_fee_deadline_hours) : $setting->booking_fee_deadline_hours }}" min="1" max="8760" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                            <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Isi jumlah jam sejak booking dibuat, misalnya 12 berarti 12 jam.</p>
                        </div>

                        <div>
                            <label for="{{ $category }}-dp" class="mb-1 block text-sm font-medium text-gray-700 dark:text-zinc-300">Down Payment / DP (Rp)</label>
                            <input id="{{ $category }}-dp" name="dp_amount" value="{{ old('payment_category') === $category ? old('dp_amount', (int) $setting->dp_amount) : (int) $setting->dp_amount }}" inputmode="numeric" data-currency-input data-currency-min="0" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                        </div>

                        <div>
                            <label for="{{ $category }}-dp-deadline" class="mb-1 block text-sm font-medium text-gray-700 dark:text-zinc-300">Deadline DP (hari)</label>
                            <input id="{{ $category }}-dp-deadline" type="number" name="dp_deadline_days" value="{{ old('payment_category') === $category ? old('dp_deadline_days', $setting->dp_deadline_days) : $setting->dp_deadline_days }}" min="1" max="3650" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                            <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">Isi jumlah hari sejak booking dibuat, misalnya 12 berarti 12 hari.</p>
                        </div>

                        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Simpan konfigurasi {{ $label }}</button>
                    </form>
                </section>
            @endforeach
        </div>

        <p class="text-sm text-zinc-600 dark:text-zinc-400">Jika deadline Booking Fee terlewati dan belum lunas, booking dihapus dan kavling dikembalikan menjadi tersedia. Jika deadline DP terlewati, sistem hanya membuat alert dan booking tetap aktif.</p>
    </div>
</x-layouts::app>
