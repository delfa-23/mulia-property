<?php

use App\Models\Division;
use App\Models\FinanceCashAccount;
use App\Models\FinanceCashFlow;
use App\Models\User;
use Illuminate\Support\Carbon;

test('finance staff can add any bank and record a plan in week four', function () {
    $this->travelTo(Carbon::parse('2026-10-12 09:00:00'));
    $division = Division::create([
        'name' => 'Finance',
        'slug' => 'finance',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->get(route('keuangan.cash-position.index'))
        ->assertSee('Minggu ke-4');

    $this->post(route('keuangan.cash-accounts.store'), [
        'name' => 'Bank Baru',
        'balance' => '1250000',
    ])
        ->assertRedirect(route('keuangan.cash-position.index'));

    $account = FinanceCashAccount::query()->where('name', 'Bank Baru')->firstOrFail();
    expect($account->account_key)->toStartWith('finance-')
        ->and((float) $account->balance)->toBe(1250000.0);

    $this->post(route('keuangan.cash-flows.store'), [
        'title' => 'Pembayaran material',
        'direction' => 'outcome',
        'status' => 'confirmed',
        'week_number' => 4,
        'due_date' => '2026-11-03',
        'amount' => 5000000,
    ])
        ->assertRedirect(route('keuangan.cash-position.index'));

    $this->assertDatabaseHas('finance_cash_flows', [
        'title' => 'Pembayaran material',
        'direction' => 'outcome',
        'due_date' => '2026-11-03',
        'created_by' => $user->id,
    ]);
    $cashFlow = FinanceCashFlow::query()->where('title', 'Pembayaran material')->firstOrFail();
    expect((float) $cashFlow->amount)->toBe(5000000.0);

    $this->get(route('keuangan.cash-position.index'))
        ->assertSee('Bank Baru')
        ->assertSee('Pembayaran material');
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

test('finance rejects a cash-flow date outside the selected week', function () {
    $this->travelTo(Carbon::parse('2026-10-12 09:00:00'));
    $division = Division::create([
        'name' => 'Finance',
        'slug' => 'finance',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'tl_finance',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->post(route('keuangan.cash-flows.store'), [
            'title' => 'Tanggal tidak sesuai',
            'direction' => 'income',
            'status' => 'forecast',
            'week_number' => 4,
            'due_date' => '2026-10-20',
            'amount' => 1000000,
        ])
        ->assertSessionHasErrors('due_date');

    $this->assertDatabaseMissing('finance_cash_flows', [
        'title' => 'Tanggal tidak sesuai',
    ]);
});

test('admin can create both finance roles with the finance division assignment', function (string $role) {
    $financeDivision = Division::create([
        'name' => 'Finance',
        'slug' => 'finance',
        'is_active' => true,
    ]);
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
