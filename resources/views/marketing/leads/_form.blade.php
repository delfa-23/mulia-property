<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="lead-name" class="mb-2 block text-sm font-medium">Nama Prospek</label>
        <input id="lead-name" name="name" value="{{ old('name', $lead->name) }}" required maxlength="255" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="lead-date-received" class="mb-2 block text-sm font-medium">Tanggal Masuk</label>
        <input id="lead-date-received" type="date" name="date_received" value="{{ old('date_received', $lead->date_received?->format('Y-m-d') ?? now()->toDateString()) }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
        @error('date_received')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="lead-phone" class="mb-2 block text-sm font-medium">No. WhatsApp</label>
        <input id="lead-phone" name="phone" value="{{ old('phone', $lead->phone) }}" maxlength="50" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="lead-domicile" class="mb-2 block text-sm font-medium">Domisili</label>
        <input id="lead-domicile" name="domicile" value="{{ old('domicile', $lead->domicile) }}" maxlength="255" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
        @error('domicile')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="lead-sales" class="mb-2 block text-sm font-medium">Marketing / Sales</label>
        <select id="lead-sales" name="sales_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
            <option value="">Belum ditentukan</option>
            @foreach($salesOptions as $salesOption)
                <option value="{{ $salesOption->id }}" @selected(old('sales_id', $lead->sales_id) == $salesOption->id)>{{ $salesOption->name }}</option>
            @endforeach
        </select>
        @error('sales_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    @if($teamMembers->isNotEmpty())
        <div>
            <label for="lead-assigned-user" class="mb-2 block text-sm font-medium">PIC CRM</label>
            <select id="lead-assigned-user" name="assigned_user_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
                <option value="">Belum ditugaskan</option>
                @foreach($teamMembers as $teamMember)
                    <option value="{{ $teamMember->id }}" @selected(old('assigned_user_id', $lead->assigned_user_id) == $teamMember->id)>{{ $teamMember->name }}</option>
                @endforeach
            </select>
            @error('assigned_user_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endif
    <div>
        <label for="lead-category" class="mb-2 block text-sm font-medium">Kategori Lead</label>
        <select id="lead-category" name="category" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
            @foreach($categories as $category)
                <option value="{{ $category }}" @selected(old('category', $lead->category ?: 'Cool') === $category)>{{ $categoryLabels[$category] ?? $category }}</option>
            @endforeach
        </select>
        @error('category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="lead-source" class="mb-2 block text-sm font-medium">Sumber Prospek</label>
        <select id="lead-source" name="source" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
            <option value="">Pilih sumber</option>
            @foreach($sources as $source)
                <option value="{{ $source }}" @selected(old('source', $lead->source) === $source)>{{ $source }}</option>
            @endforeach
        </select>
        @error('source')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="lead-last-contact" class="mb-2 block text-sm font-medium">Tanggal Kontak Terakhir</label>
        <input id="lead-last-contact" type="date" name="last_contact_at" value="{{ old('last_contact_at', $lead->last_contact_at?->format('Y-m-d')) }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
        @error('last_contact_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label for="lead-last-progress" class="mb-2 block text-sm font-medium">Progres / Catatan Terakhir</label>
        <textarea id="lead-last-progress" name="last_progress" rows="3" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">{{ old('last_progress', $lead->last_progress) }}</textarea>
        @error('last_progress')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
