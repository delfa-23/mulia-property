<?php

use App\Models\Division;
use App\Models\FinanceCashAccount;
use App\Models\FinanceCashFlow;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Carbon;

function financeCashFlowUser(string $role = 'staff_finance', string $divisionSlug = 'finance'): User
{
    $division = Division::create([
        'name' => ucfirst($divisionSlug),
        'slug' => $divisionSlug,
        'is_active' => true,
    ]);

    return User::factory()->create([
        'role' => $role,
        'division_id' => $division->id,
    ]);
}

it('shows finance cash balances and forecasts for each of the next four weeks', function () {
    $this->travelTo(Carbon::parse('2026-10-12 09:00:00'));
    $user = financeCashFlowUser();
    $property = Property::create(['name' => 'Perumahan Proyeksi']);
    $account = FinanceCashAccount::query()->where('account_key', 'bank_bsn')->firstOrFail();
    $account->update(['balance' => 2500000]);

    FinanceCashFlow::query()->create([
        'property_id' => $property->id,
        'title' => 'Pelunasan booking',
        'direction' => 'income',
        'status' => 'confirmed',
        'due_date' => '2026-10-18',
        'amount' => 10000000,
    ]);
    FinanceCashFlow::query()->create([
        'title' => 'Tanda jadi potensial',
        'direction' => 'income',
        'status' => 'potential',
        'due_date' => '2026-10-20',
        'amount' => 5000000,
    ]);
    FinanceCashFlow::query()->create([
        'title' => 'Target penjualan',
        'direction' => 'income',
        'status' => 'forecast',
        'due_date' => '2026-11-08',
        'amount' => 3000000,
    ]);
    FinanceCashFlow::query()->create([
        'title' => 'Biaya event',
        'direction' => 'outcome',
        'status' => 'confirmed',
        'due_date' => '2026-10-18',
        'amount' => 2000000,
    ]);
    FinanceCashFlow::query()->create([
        'title' => 'Biaya promosi',
        'direction' => 'outcome',
        'status' => 'forecast',
        'due_date' => '2026-11-09',
        'amount' => 9000000,
    ]);

    $this->actingAs($user)
        ->get(route('keuangan.cash-position.index'))
        ->assertSee('Bank BSN')
        ->assertSee('Bank BSI')
        ->assertSee('Bank Sumut Syariah')
        ->assertSee('Petty Cash')
        ->assertSee('Rp 2.500.000')
        ->assertSee('Minggu ke-1')
        ->assertSee('Minggu ke-4')
        ->assertViewHas('cashFlowForecasts', function ($forecasts): bool {
            return $forecasts[1]['income_confirmed'] === 10000000.0
                && $forecasts[1]['outcome_confirmed'] === 2000000.0
                && $forecasts[2]['income_potential'] === 5000000.0
                && $forecasts[4]['income_forecast'] === 3000000.0
                && $forecasts[4]['outcome_forecast'] === 0.0;
        });
});

it('shows a selected past month as an archived history with monthly totals and details', function () {
    $this->travelTo(Carbon::parse('2026-10-15 09:00:00'));
    $user = financeCashFlowUser();
    $archivedIncome = FinanceCashFlow::query()->create([
        'title' => 'Pemasukan arsip September',
        'direction' => 'income',
        'status' => 'confirmed',
        'due_date' => '2026-09-12',
        'amount' => 12000000,
    ]);
    $archivedOutcome = FinanceCashFlow::query()->create([
        'title' => 'Pengeluaran arsip September',
        'direction' => 'outcome',
        'status' => 'forecast',
        'due_date' => '2026-09-21',
        'amount' => 4000000,
    ]);
    FinanceCashFlow::query()->create([
        'title' => 'Rencana bulan berjalan',
        'direction' => 'income',
        'status' => 'confirmed',
        'due_date' => '2026-10-20',
        'amount' => 7000000,
    ]);

    $this->actingAs($user)
        ->get(route('keuangan.cash-position.index', ['history_month' => '2026-09']))
        ->assertSee('Diarsipkan otomatis')
        ->assertSee('September 2026')
        ->assertSee('Pemasukan arsip September')
        ->assertSee('Pengeluaran arsip September')
        ->assertViewHas('selectedHistoryMonth', '2026-09')
        ->assertViewHas('historyIsArchived', true)
        ->assertViewHas('historySummary', fn ($summary): bool => (float) $summary->income_total === 12000000.0
            && (float) $summary->outcome_total === 4000000.0)
        ->assertViewHas('cashFlowHistory', fn ($history): bool => $history->total() === 2
            && $history->getCollection()->contains('id', $archivedIncome->id)
            && $history->getCollection()->contains('id', $archivedOutcome->id)
            && $history->getCollection()->doesntContain('title', 'Rencana bulan berjalan'));
});

it('rejects a month that is not available in the finance history', function () {
    $user = financeCashFlowUser();

    $this->actingAs($user)
        ->get(route('keuangan.cash-position.index', ['history_month' => '2020-01']))
        ->assertSessionHasErrors('history_month');
});

it('updates one finance cash account balance', function () {
    $user = financeCashFlowUser();
    $account = FinanceCashAccount::query()->where('account_key', 'bank_bsi')->firstOrFail();

    $this->actingAs($user)
        ->put(route('keuangan.cash-accounts.update', $account), ['balance' => '1250000.50'])
        ->assertRedirect(route('keuangan.cash-position.index'));

    $account->refresh();

    expect((float) $account->balance)->toBe(1250000.5)
        ->and($account->updated_by)->toBe($user->id);
});

it('rejects a negative finance cash account balance', function () {
    $user = financeCashFlowUser();
    $account = FinanceCashAccount::query()->where('account_key', 'bank_bsn')->firstOrFail();
    $account->update(['balance' => 750000]);

    $this->actingAs($user)
        ->put(route('keuangan.cash-accounts.update', $account), ['balance' => -1])
        ->assertSessionHasErrors('balance');

    expect((float) $account->fresh()->balance)->toBe(750000.0);
});

it('creates and displays an escaped finance cash flow plan', function () {
    $this->travelTo(Carbon::parse('2026-10-12 09:00:00'));
    $user = financeCashFlowUser();

    $this->actingAs($user)
        ->post(route('keuangan.cash-flows.store'), [
            'title' => '<script>alert(1)</script>',
            'direction' => 'income',
            'status' => 'potential',
            'week_number' => 1,
            'due_date' => '2026-10-15',
            'amount' => 8500000,
        ])
        ->assertRedirect(route('keuangan.cash-position.index'));

    $plan = FinanceCashFlow::query()->firstOrFail();

    expect($plan->created_by)->toBe($user->id)
        ->and($plan->direction)->toBe('income')
        ->and((float) $plan->amount)->toBe(8500000.0);

    $this->actingAs($user)
        ->get(route('keuangan.cash-position.index'))
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('rejects invalid finance cash flow plans without storing them', function () {
    $this->travelTo(Carbon::parse('2026-10-12 09:00:00'));
    $user = financeCashFlowUser();

    $this->actingAs($user)
        ->post(route('keuangan.cash-flows.store'), [
            'title' => '',
            'direction' => 'transfer',
            'status' => 'unknown',
            'week_number' => 1,
            'due_date' => '2026-10-11',
            'amount' => 0,
        ])
        ->assertSessionHasErrors(['title', 'direction', 'status', 'due_date', 'amount']);

    $this->actingAs($user)
        ->post(route('keuangan.cash-flows.store'), [
            'title' => 'Rencana di luar periode',
            'direction' => 'income',
            'status' => 'confirmed',
            'week_number' => 4,
            'due_date' => '2026-10-20',
            'amount' => 1000,
        ])
        ->assertSessionHasErrors('due_date');

    $this->assertDatabaseCount('finance_cash_flows', 0);
});

it('forbids users outside finance from changing cash balances', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $account = FinanceCashAccount::query()->where('account_key', 'bank_bsn')->firstOrFail();

    $this->actingAs($user)
        ->put(route('keuangan.cash-accounts.update', $account), ['balance' => 500])
        ->assertForbidden();

    expect($account->fresh()->balance)->toBeNull();
});

it('allows finance users to remove an incorrect cash flow plan', function () {
    $user = financeCashFlowUser();
    $plan = FinanceCashFlow::query()->create([
        'title' => 'Rencana keliru',
        'direction' => 'outcome',
        'status' => 'confirmed',
        'due_date' => today()->addDays(2),
        'amount' => 250000,
    ]);

    $this->actingAs($user)
        ->delete(route('keuangan.cash-flows.destroy', $plan))
        ->assertRedirect(route('keuangan.cash-position.index', [
            'history_month' => $plan->due_date->format('Y-m'),
        ]));

    $this->assertDatabaseMissing('finance_cash_flows', ['id' => $plan->id]);
});
