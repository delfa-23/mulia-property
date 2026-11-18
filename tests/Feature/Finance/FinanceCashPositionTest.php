<?php

use App\Models\Division;
use App\Models\FinanceCashAccount;
use App\Models\FinanceCashFlow;
use App\Models\User;
use Illuminate\Support\Carbon;

test('finance staff can add any bank and the due date automatically assigns a plan to its monthly week', function () {
    $this->travelTo(Carbon::parse('2026-10-12 09:00:00'));
    $division = Division::query()->firstOrCreate(
        ['slug' => 'finance'],
        ['name' => 'Finance', 'is_active' => true],
    );
    $user = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->get(route('keuangan.cash-position.index'))
        ->assertSee('Minggu ke-4')
        ->assertSee('name="forecast_month"', false)
        ->assertSee('value="2027-12"', false)
        ->assertSee('Desember 2027')
        ->assertSee('01/10 – 07/10/2026')
        ->assertSee('08/10 – 14/10/2026')
        ->assertSee('15/10 – 21/10/2026')
        ->assertSee('22/10 – 31/10/2026')
        ->assertViewHas('forecastMonthOptions', fn (array $options): bool => count($options) === 15);
    $this->assertDatabaseCount('finance_cash_flows', 0);

    $this->post(route('keuangan.cash-accounts.store'), [
        'name' => 'Bank Baru',
        'balance' => '1250000',
    ])
        ->assertRedirect(route('keuangan.cash-position.index'));

    $account = FinanceCashAccount::query()->where('name', 'Bank Baru')->firstOrFail();
    expect($account->account_key)->toStartWith('finance-')
        ->and((float) $account->balance)->toBe(1250000.0);
    $this->assertDatabaseHas('finance_cash_account_balance_histories', [
        'cash_account_id' => $account->id,
        'account_name' => 'Bank Baru',
        'previous_balance' => null,
        'new_balance' => '1250000.00',
        'changed_by' => $user->id,
    ]);

    $this->post(route('keuangan.cash-flows.store'), [
        'forecast_month' => '2026-10',
        'title' => 'Pembayaran material',
        'direction' => 'outcome',
        'status' => 'confirmed',
        'due_date' => '2026-10-26',
        'amount' => 5000000,
    ])
        ->assertRedirect(route('keuangan.cash-position.index', ['forecast_month' => '2026-10']));

    $this->assertDatabaseHas('finance_cash_flows', [
        'title' => 'Pembayaran material',
        'direction' => 'outcome',
        'created_by' => $user->id,
    ]);
    $cashFlow = FinanceCashFlow::query()->where('title', 'Pembayaran material')->firstOrFail();
    expect((float) $cashFlow->amount)->toBe(5000000.0);
    expect($cashFlow->due_date->toDateString())->toBe('2026-10-26');

    $this->get(route('keuangan.cash-position.index'))
        ->assertSee('Bank Baru')
        ->assertSee('Total Saldo Bank & Kas')
        ->assertSee('Rp 1.250.000')
        ->assertSee('Pembayaran material')
        ->assertSee('26/10/2026');
});

test('finance cash position totals all recorded bank and cash balances', function () {
    $division = Division::query()->firstOrCreate(
        ['slug' => 'finance'],
        ['name' => 'Finance', 'is_active' => true],
    );
    $user = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $division->id,
    ]);
    FinanceCashAccount::query()->create([
        'account_key' => 'finance-total-bank',
        'name' => 'Bank Utama',
        'balance' => 1250000,
    ]);
    FinanceCashAccount::query()->create([
        'account_key' => 'finance-total-cash',
        'name' => 'Kas Kantor',
        'balance' => 750000,
    ]);
    FinanceCashAccount::query()->create([
        'account_key' => 'finance-total-unset',
        'name' => 'Rekening Belum Diisi',
        'balance' => null,
    ]);

    $this->actingAs($user)
        ->get(route('keuangan.cash-position.index'))
        ->assertSee('Total Saldo Bank & Kas')
        ->assertSee('Rp 2.000.000')
        ->assertSee('Rekening Belum Diisi');
});

test('marketing users cannot access the finance cash position', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->get(route('keuangan.cash-position.index'))
        ->assertForbidden();
});

test('finance staff can delete a cash account', function () {
    $division = Division::query()->firstOrCreate(
        ['slug' => 'finance'],
        ['name' => 'Finance', 'is_active' => true],
    );
    $user = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $division->id,
    ]);
    $account = FinanceCashAccount::query()->create([
        'account_key' => 'finance-delete-test',
        'name' => 'Bank yang Dihapus',
        'balance' => 250000,
        'updated_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->delete(route('keuangan.cash-accounts.destroy', $account))
        ->assertRedirect(route('keuangan.cash-position.index'))
        ->assertSessionHas('success', 'Rekening berhasil dihapus.');

    $this->assertDatabaseMissing('finance_cash_accounts', ['id' => $account->id]);
});

test('marketing users cannot delete a finance cash account', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $account = FinanceCashAccount::query()->create([
        'account_key' => 'finance-protected-account',
        'name' => 'Bank Finance',
        'balance' => 250000,
    ]);

    $this->actingAs($user)
        ->delete(route('keuangan.cash-accounts.destroy', $account))
        ->assertForbidden();

    $this->assertDatabaseHas('finance_cash_accounts', ['id' => $account->id]);
});

test('finance rejects a due date before today or outside the selected month', function (string $forecastMonth, string $dueDate, string $errorMessage) {
    $this->travelTo(Carbon::parse('2026-10-12 09:00:00'));
    $division = Division::query()->firstOrCreate(
        ['slug' => 'finance'],
        ['name' => 'Finance', 'is_active' => true],
    );
    $user = User::factory()->create([
        'role' => 'tl_finance',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->post(route('keuangan.cash-flows.store'), [
            'forecast_month' => $forecastMonth,
            'title' => 'Tanggal tidak sesuai',
            'direction' => 'income',
            'status' => 'forecast',
            'due_date' => $dueDate,
            'amount' => 1000000,
        ])
        ->assertInvalid(['due_date' => $errorMessage]);

    $this->assertDatabaseMissing('finance_cash_flows', [
        'title' => 'Tanggal tidak sesuai',
    ]);
})->with([
    'before today' => ['2026-10', '2026-10-11', 'Tanggal jatuh tempo tidak boleh sebelum hari ini.'],
    'outside the selected month' => [
        '2026-10',
        '2026-11-01',
        'Tanggal jatuh tempo harus berada pada bulan yang dipilih dan tidak boleh sudah lewat.',
    ],
]);

test('the new month starts new weekly periods and moves the previous month plans into history', function () {
    $this->travelTo(Carbon::parse('2026-11-01 09:00:00'));
    $division = Division::query()->firstOrCreate(
        ['slug' => 'finance'],
        ['name' => 'Finance', 'is_active' => true],
    );
    $user = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $division->id,
    ]);
    $previousMonthPlan = FinanceCashFlow::query()->create([
        'title' => 'Rencana bulan lalu',
        'direction' => 'outcome',
        'status' => 'confirmed',
        'due_date' => '2026-10-31',
        'amount' => 500000,
        'created_by' => $user->id,
    ]);
    $currentMonthPlan = FinanceCashFlow::query()->create([
        'title' => 'Rencana bulan ini',
        'direction' => 'outcome',
        'status' => 'confirmed',
        'due_date' => '2026-11-01',
        'amount' => 750000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('keuangan.cash-position.index'))
        ->assertViewHas('selectedHistoryMonth', '2026-10')
        ->assertSee('01/11 – 07/11/2026')
        ->assertSee('Rencana bulan ini')
        ->assertSee('Rencana bulan lalu')
        ->assertSee('Diarsipkan otomatis')
        ->assertViewHas('cashFlowForecasts', fn ($forecasts): bool => $forecasts[1]['plans']->contains('id', $currentMonthPlan->id)
            && $forecasts->every(fn ($forecast): bool => ! $forecast['plans']->contains('id', $previousMonthPlan->id)))
        ->assertViewHas('cashFlowHistory', fn ($history): bool => $history->getCollection()->contains('id', $previousMonthPlan->id));
});

test('finance can select and schedule a future month through December 2027 without precreating plans', function () {
    $this->travelTo(Carbon::parse('2026-11-01 09:00:00'));
    $division = Division::query()->firstOrCreate(
        ['slug' => 'finance'],
        ['name' => 'Finance', 'is_active' => true],
    );
    $user = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->get(route('keuangan.cash-position.index', ['forecast_month' => '2027-12']))
        ->assertSee('Rencana Pemasukan & Pengeluaran — Desember 2027')
        ->assertSee('22/12 – 31/12/2027')
        ->assertViewHas('selectedForecastMonth', '2027-12')
        ->assertViewHas('historyMonthLabels', fn ($options): bool => $options->isEmpty());
    $this->assertDatabaseCount('finance_cash_flows', 0);

    $this->post(route('keuangan.cash-flows.store'), [
        'forecast_month' => '2027-12',
        'title' => 'Pembayaran akhir tahun',
        'direction' => 'outcome',
        'status' => 'confirmed',
        'due_date' => '2027-12-31',
        'amount' => 1250000,
    ])
        ->assertRedirect(route('keuangan.cash-position.index', ['forecast_month' => '2027-12']));

    $this->assertDatabaseHas('finance_cash_flows', [
        'title' => 'Pembayaran akhir tahun',
        'direction' => 'outcome',
    ]);
    $this->get(route('keuangan.cash-position.index', ['forecast_month' => '2027-12']))
        ->assertSee('Pembayaran akhir tahun')
        ->assertSee('31/12/2027')
        ->assertViewHas('cashFlowForecasts', fn ($forecasts): bool => $forecasts[4]['plans']->contains(
            'title',
            'Pembayaran akhir tahun',
        ));
});

test('admin can create both finance roles with the finance division assignment', function (string $role) {
    $financeDivision = Division::query()->firstOrCreate(
        ['slug' => 'finance'],
        ['name' => 'Finance', 'is_active' => true],
    );
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.users.create'))
        ->assertSee('TL Finance')
        ->assertSee('Staff Finance');

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Finance Test User',
            'email' => "{$role}@example.test",
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => $role,
        ])
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'email' => "{$role}@example.test",
        'role' => $role,
        'division_id' => $financeDivision->id,
    ]);
})->with(['tl_finance', 'staff_finance']);
