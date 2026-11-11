@php
    $customerMode = old('customer_mode', 'existing');
    $paymentTermsLocked = $paymentTermsLocked ?? false;
    $akadFeeLocked = $paymentTermsLocked && ! auth()->user()->isAdmin();
    $currentAkadFee = old('akad_fee', $booking?->akad_fee ?? 0);
@endphp

<div
    x-data="{
        customerMode: @js($customerMode),
        housePrice: Number(@js($booking?->house_price_snapshot ?? $booking?->lot?->house_price ?? 0)),
        akadFeeAmount: Number(@js($currentAkadFee)),
        formatRupiah(value) {
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value || 0);
        },
        normalizeRupiah(value) {
            return Number(String(value).replace(/[^\d,]/g, '').replace(',', '.')) || 0;
        }
    }"
    class="space-y-5"
>
    <div>
        <label for="booking-property" class="mb-2 block text-sm font-medium">Perumahan</label>
        <select id="booking-property" name="property_id" data-property-select required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
            <option value="">Pilih Perumahan</option>
            @foreach($properties as $property)
                <option value="{{ $property->id }}" @selected(old('property_id', request('property_id', $booking?->lot?->block?->property_id)) == $property->id)>{{ $property->name }}</option>
            @endforeach
        </select>
        @error('property_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="booking-lot" class="mb-2 block text-sm font-medium">Kavling</label>
        <select
            id="booking-lot"
            name="lot_id"
            data-lot-select
            required
            class="w-full rounded-lg border border-zinc-300 px-3 py-2"
            @change="housePrice = Number($event.target.selectedOptions[0].dataset.price || 0)"
        >
            <option value="">Pilih kavling</option>
            @foreach($lots as $lot)
                <option value="{{ $lot->id }}" data-property-id="{{ $lot->block->property_id }}" data-price="{{ $lot->house_price }}" @selected(old('lot_id', request('lot_id', $booking?->lot_id)) == $lot->id)>
                    {{ $lot->block->name }} / {{ $lot->lot_number }} — {{ ucfirst($lot->status) }}
                </option>
            @endforeach
        </select>
        @error('lot_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        <p class="mt-2 text-sm text-zinc-500">Harga kavling: Rp <span x-text="formatRupiah(housePrice)"></span></p>
    </div>

    <div class="space-y-4 rounded-xl border border-zinc-200 p-4">
        <div>
            <label for="customer-mode" class="mb-2 block text-sm font-medium">Data Customer</label>
            <select id="customer-mode" name="customer_mode" x-model="customerMode" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                <option value="existing">Pilih customer terdaftar</option>
                <option value="new">Isi customer baru</option>
            </select>
        </div>

        <div x-show="customerMode === 'existing'" x-cloak>
            <label for="customer-id" class="mb-2 block text-sm font-medium">Nama / NIK</label>
            <select id="customer-id" name="customer_id" :disabled="customerMode !== 'existing'" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                <option value="">Pilih customer</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(old('customer_id', $booking?->customer_id) == $customer->id)>
                        {{ $customer->name }}{{ $customer->nik ? ' — '.$customer->nik : '' }}
                    </option>
                @endforeach
            </select>
            @error('customer_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <fieldset x-show="customerMode === 'new'" x-cloak :disabled="customerMode !== 'new'" class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="new-customer-name" class="mb-2 block text-sm font-medium">Nama Lengkap</label>
                <input id="new-customer-name" name="new_customer[name]" value="{{ old('new_customer.name') }}" maxlength="255" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                @error('new_customer.name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="new-customer-nik" class="mb-2 block text-sm font-medium">NIK</label>
                <input id="new-customer-nik" name="new_customer[nik]" value="{{ old('new_customer.nik') }}" inputmode="numeric" maxlength="16" pattern="[0-9]{16}" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                @error('new_customer.nik')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="new-customer-phone" class="mb-2 block text-sm font-medium">Telepon</label>
                <input id="new-customer-phone" name="new_customer[phone]" value="{{ old('new_customer.phone') }}" maxlength="50" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                @error('new_customer.phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="new-customer-email" class="mb-2 block text-sm font-medium">Email</label>
                <input id="new-customer-email" type="email" name="new_customer[email]" value="{{ old('new_customer.email') }}" maxlength="255" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                @error('new_customer.email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="new-customer-birth-place" class="mb-2 block text-sm font-medium">Tempat Lahir</label>
                <input id="new-customer-birth-place" name="new_customer[birth_place]" value="{{ old('new_customer.birth_place') }}" maxlength="255" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
            </div>
            <div>
                <label for="new-customer-birth-date" class="mb-2 block text-sm font-medium">Tanggal Lahir</label>
                <input id="new-customer-birth-date" type="date" name="new_customer[birth_date]" value="{{ old('new_customer.birth_date') }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
            </div>
            <div>
                <label for="new-customer-marital-status" class="mb-2 block text-sm font-medium">Status Pernikahan</label>
                <select id="new-customer-marital-status" name="new_customer[marital_status]" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                    <option value="">Pilih status</option>
                    <option value="Belum Menikah" @selected(old('new_customer.marital_status') === 'Belum Menikah')>Belum Menikah</option>
                    <option value="Menikah" @selected(old('new_customer.marital_status') === 'Menikah')>Menikah</option>
                </select>
            </div>
            <div>
                <label for="new-customer-occupation" class="mb-2 block text-sm font-medium">Pekerjaan</label>
                <input id="new-customer-occupation" name="new_customer[occupation]" value="{{ old('new_customer.occupation') }}" maxlength="255" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
            </div>
            <div class="sm:col-span-2">
                <label for="new-customer-address" class="mb-2 block text-sm font-medium">Alamat</label>
                <textarea id="new-customer-address" name="new_customer[address]" rows="2" class="w-full rounded-lg border border-zinc-300 px-3 py-2">{{ old('new_customer.address') }}</textarea>
            </div>
        </fieldset>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="booking-date" class="mb-2 block text-sm font-medium">Tanggal Booking</label>
            <input id="booking-date" type="date" name="booking_date" value="{{ old('booking_date', $booking?->booking_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
            @error('booking_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="blocking-until" class="mb-2 block text-sm font-medium">Batas Booking (wajib untuk status booking)</label>
            <input id="blocking-until" type="datetime-local" name="blocking_until" value="{{ old('blocking_until', $booking?->blocking_until?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
            <p class="mt-1 text-xs text-zinc-500">Booking aktif akan dibatalkan otomatis setelah waktu ini.</p>
            @error('blocking_until')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-950 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-100">
            <p class="font-semibold">Skema pembayaran booking</p>
            <p class="mt-1">Total pembayaran booking sampai lunas adalah 100% harga rumah: Rp <span x-text="formatRupiah(housePrice)"></span>.</p>
            <p>Nominal pembayaran mengikuti jadwal yang diatur admin untuk perumahan ini.</p>
        </div>
        <p class="text-sm text-zinc-600">Pembayaran booking dikelola melalui jadwal yang ditentukan admin di menu Pembayaran.</p>
        <div class="max-w-xl">
            <label for="akad-fee" class="mb-2 block text-sm font-medium">Biaya Akad (Rp)</label>
            <input id="akad-fee" type="text" name="akad_fee" value="{{ $currentAkadFee }}" inputmode="decimal" data-currency-input data-currency-min="0" x-on:input="akadFeeAmount = normalizeRupiah($event.target.value)" @readonly($akadFeeLocked) required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
            @error('akad_fee')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
    @if(isset($paymentSummary))
        @php
            $bookingProgress = collect($paymentSummary['stages'])->whereIn('stage', ['dp', 'booking', 'booking_balance'])->sum('remaining');
            $akadProgress = collect($paymentSummary['stages'])->firstWhere('key', 'akad_fee');
        @endphp
        <section class="space-y-2 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm dark:border-blue-900 dark:bg-blue-950/30" aria-label="Ringkasan pembayaran booking">
            <h3 class="font-semibold text-blue-950 dark:text-blue-100">Progres pembayaran booking</h3>
            <p>Target pembayaran booking: 100% harga rumah (Rp {{ number_format($paymentSummary['house_price'], 0, ',', '.') }})</p>
            <p>Sudah dibayar: Rp {{ number_format($paymentSummary['booking_paid'], 0, ',', '.') }} · {{ number_format($paymentSummary['booking_paid_percentage'], 2) }}% dari harga rumah</p>
            <p>Sisa pembayaran booking: Rp {{ number_format($bookingProgress, 0, ',', '.') }}.</p>
            <div class="h-2 overflow-hidden rounded-full bg-blue-100 dark:bg-blue-900">
                <div class="h-full rounded-full bg-blue-600" style="width: {{ $paymentSummary['booking_paid_percentage'] }}%"></div>
            </div>
        </section>
        <section class="space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900 dark:bg-amber-950/30" aria-label="Ringkasan pembayaran biaya akad">
            <h3 class="font-semibold text-amber-950 dark:text-amber-100">Progres biaya akad</h3>
            <p>Total biaya akad: Rp {{ number_format($akadProgress['due'], 0, ',', '.') }}</p>
            <p>Sudah dibayar: Rp {{ number_format($akadProgress['paid'], 0, ',', '.') }} · {{ number_format($akadProgress['paid_percentage'], 2) }}%</p>
            <p>Sisa biaya akad: Rp {{ number_format($akadProgress['remaining'], 0, ',', '.') }}.</p>
            <div class="h-2 overflow-hidden rounded-full bg-amber-100 dark:bg-amber-900">
                <div class="h-full rounded-full bg-amber-600" style="width: {{ $akadProgress['paid_percentage'] }}%"></div>
            </div>
        </section>
    @endif
    @if($paymentTermsLocked && ! auth()->user()->isAdmin())
        <p class="text-sm text-amber-700">Harga dan biaya akad dikunci setelah pembayaran dicatat. Total pembayaran booking dan biaya akad yang sudah dibayar tidak dapat dikurangi.</p>
    @elseif(auth()->user()->isAdmin())
        <p class="text-sm text-amber-700">Sebagai admin, biaya akad tetap dapat diperbarui meskipun pembayaran sudah dicatat. Nilai baru tidak boleh lebih kecil dari biaya akad yang sudah dibayar.</p>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="sales-id" class="mb-2 block text-sm font-medium">Sales</label>
            <select id="sales-id" name="sales_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                <option value="">Pilih Sales</option>
                @foreach($salesUsers as $sales)
                    <option value="{{ $sales->id }}" @selected(old('sales_id', $booking?->sales_id) == $sales->id)>{{ $sales->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="lead-id" class="mb-2 block text-sm font-medium">Prospek Sumber</label>
            <select id="lead-id" name="lead_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                <option value="">Tidak terkait prospek</option>
                @foreach($leads as $leadOption)
                    <option value="{{ $leadOption->id }}" @selected(old('lead_id', $selectedLeadId) == $leadOption->id)>
                        {{ $leadOption->name }} — {{ $leadOption->date_received?->format('d/m/Y') }}
                    </option>
                @endforeach
            </select>
            @error('lead_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        @if($booking)
            <div>
                <label for="status" class="mb-2 block text-sm font-medium">Status</label>
                <select id="status" name="status" required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                    @foreach(['booking', 'process', 'akad', 'finish', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $booking->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @else
            <input type="hidden" name="status" value="booking">
        @endif
    </div>

    <div>
        <label for="booking-notes" class="mb-2 block text-sm font-medium">Catatan</label>
        <textarea id="booking-notes" name="notes" rows="3" class="w-full rounded-lg border border-zinc-300 px-3 py-2">{{ old('notes', $booking?->notes) }}</textarea>
        @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <section class="space-y-4 rounded-xl border border-zinc-200 p-4">
        <div>
            <h3 class="font-semibold">Lokasi Kerja untuk Survei Berkas</h3>
            <p class="mt-1 text-sm text-zinc-500">Masukkan tautan Google Maps pemohon dan pasangan jika tersedia.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="applicant-work-location-url" class="mb-2 block text-sm font-medium">Link Google Maps Lokasi Kerja Pemohon</label>
                <input id="applicant-work-location-url" type="url" name="applicant_work_location_url" value="{{ old('applicant_work_location_url', $booking?->applicant_work_location_url) }}" maxlength="2048" placeholder="https://maps.google.com/..." class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                @error('applicant_work_location_url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="spouse-work-location-url" class="mb-2 block text-sm font-medium">Link Google Maps Lokasi Kerja Pasangan</label>
                <input id="spouse-work-location-url" type="url" name="spouse_work_location_url" value="{{ old('spouse_work_location_url', $booking?->spouse_work_location_url) }}" maxlength="2048" placeholder="https://maps.google.com/..." class="w-full rounded-lg border border-zinc-300 px-3 py-2">
                @error('spouse_work_location_url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>
</div>
