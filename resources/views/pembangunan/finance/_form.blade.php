@php
    $budgetField = $division === 'marketing' ? 'marketing_budget_id' : 'construction_budget_id';
@endphp

<div>
    <label for="expense-budget" class="mb-2 block text-sm font-semibold">Pilih RAB {{ ucfirst($division) }}</label>
    <select
        id="expense-budget"
        name="{{ $budgetField }}"
        data-expense-budget-select
        required
        class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
    >
        <option value="">Pilih RAB {{ ucfirst($division) }}</option>
        @foreach($expenseBudgets as $expenseBudget)
            @php
                $budgetProperty = $expenseBudget->property
                    ?? $expenseBudget->lot?->block?->property
                    ?? $expenseBudget->facility?->property;
                $budgetTarget = $expenseBudget->lot
                    ? 'Kavling '.$expenseBudget->lot->block?->name.'/'.$expenseBudget->lot->lot_number
                    : ($expenseBudget->facility ? 'Fasilitas '.$expenseBudget->facility->name : 'Project umum');
            @endphp
            <option
                value="{{ $expenseBudget->id }}"
                @selected(old($budgetField, $expenseTransaction?->budget_id) == $expenseBudget->id)
            >{{ $expenseBudget->name }} — {{ $expenseBudget->category }} · {{ $budgetProperty?->name ?? 'Semua perumahan' }} · {{ $budgetTarget }} · Rp {{ number_format((float) $expenseBudget->amount, 0, ',', '.') }}</option>
        @endforeach
    </select>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
        Project, sasaran, dan kategori akan mengikuti RAB yang dipilih. Isi tanggal, nominal, dan keterangan pengeluaran.
    </p>
    @error($budgetField)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="transaction-date" class="mb-2 block text-sm font-medium">Tanggal</label>
        <input id="transaction-date" type="date" name="transaction_date" value="{{ old('transaction_date', $expenseTransaction?->transaction_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
        @error('transaction_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="expense-amount" class="mb-2 block text-sm font-medium">Nominal (Rp)</label>
        <input id="expense-amount" type="text" name="amount" value="{{ old('amount', $expenseTransaction?->amount) }}" inputmode="decimal" data-currency-input data-currency-min="0.01" required class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">
        @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label for="expense-description" class="mb-2 block text-sm font-medium">Keterangan</label>
    <textarea id="expense-description" name="description" rows="4" class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800">{{ old('description', $expenseTransaction?->description) }}</textarea>
    @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
