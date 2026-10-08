<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="mb-2 block text-sm font-medium">Nama Sales</label>
        <input name="name" value="{{ old('name', $sale?->name) }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium">Telepon</label>
        <input name="phone" value="{{ old('phone', $sale?->phone) }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium">Email</label>
        <input type="email" name="email" value="{{ old('email', $sale?->email) }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2">
        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label class="mb-2 block text-sm font-medium">Alamat</label>
        <textarea name="address" rows="3" class="w-full rounded-lg border border-zinc-300 px-3 py-2">{{ old('address', $sale?->address) }}</textarea>
        @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <label class="flex items-center gap-2 text-sm sm:col-span-2">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $sale?->is_active ?? true))>
        Sales aktif dan dapat dipilih pada booking
    </label>
</div>
