<?php

use App\Models\Division;
use App\Models\MarketingCashAccount;
use App\Models\MarketingCashFlow;
use App\Models\Property;
use App\Models\User;

function marketingCashFlowUser(string $role = 'staff_marketing', string $divisionSlug = 'marketing'): User
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

it('shows cash balances and cumulative income and outcome forecasts by horizon', function () {
    $user = marketingCashFlowUser();
    $property = Property::create(['name' => 'Perumahan Proyeksi']);
    $account = MarketingCashAccount::query()->where('account_key', 'bank_bsn')->firstOrFail();
    $account->update(['balance' => 2500000]);

    MarketingCashFlow::query()->create([
        'property_id' => $property->id,
        'title' => 'Pelunasan booking',
        'direction' => 'income',
        'status' => 'confirmed',
        'due_date' => today()->addDays(7),
        'amount' => 10000000,
    ]);
    MarketingCashFlow::query()->create([
        'title' => 'Tanda jadi potensial',
        'direction' => 'income',
        'status' => 'potential',
        'due_date' => today()->addDays(8),
        'amount' => 5000000,
    ]);
    MarketingCashFlow::query()->create([
        'title' => 'Target penjualan',
        'direction' => 'income',
        'status' => 'forecast',
        'due_date' => today()->addDays(30),
        'amount' => 3000000,
    ]);
    MarketingCashFlow::query()->create([
        'title' => 'Biaya event',
        'direction' => 'outcome',
        'status' => 'confirmed',
        'due_date' => today()->addDays(7),
        'amount' => 2000000,
    ]);
    MarketingCashFlow::query()->create([
        'title' => 'Biaya promosi',
        'direction' => 'outcome',
        'status' => 'forecast',
        'due_date' => today()->addDays(31),
        'amount' => 9000000,
    ]);

    $this->actingAs($user)
        ->get(route('marketing.dashboard', ['property_id' => $property->id]))
        ->assertSee('Bank BSN')
        ->assertSee('Bank BSI')
        ->assertSee('Bank Sumut Syariah')
        ->assertSee('Petty Cash')
        ->assertSee('Rp 2.500.000')
        ->assertSee('Rencana Income')
        ->assertSee('Rencana Outcome')
        ->assertViewHas('cashFlowForecasts', function ($forecasts): bool {
            return $forecasts[7]['income_confirmed'] === 10000000.0
                && $forecasts[7]['income_potential'] === 0.0
                && $forecasts[7]['outcome_confirmed'] === 2000000.0
                && $forecasts[14]['income_potential'] === 5000000.0
                && $forecasts[30]['income_forecast'] === 3000000.0
                && $forecasts[30]['outcome_forecast'] === 0.0;
        });
});

it('updates one marketing cash account balance', function () {
    $user = marketingCashFlowUser();
    $account = MarketingCashAccount::query()->where('account_key', 'bank_bsi')->firstOrFail();

    $this->actingAs($user)
        ->put(route('marketing.cash-accounts.update', $account), ['balance' => '1250000.50'])
        ->assertRedirect(route('marketing.dashboard'));

    $account->refresh();

    expect((float) $account->balance)->toBe(1250000.5)
        ->and($account->updated_by)->toBe($user->id);
});

it('rejects a negative marketing cash account balance', function () {
    $user = marketingCashFlowUser();
    $account = MarketingCashAccount::query()->where('account_key', 'bank_bsn')->firstOrFail();
    $account->update(['balance' => 750000]);

    $this->actingAs($user)
        ->put(route('marketing.cash-accounts.update', $account), ['balance' => -1])
        ->assertSessionHasErrors('balance');

    expect((float) $account->fresh()->balance)->toBe(750000.0);
});

it('creates and displays an escaped marketing cash flow plan', function () {
    $user = marketingCashFlowUser();

    $this->actingAs($user)
        ->post(route('marketing.cash-flows.store'), [
            'title' => '<script>alert(1)</script>',
            'direction' => 'income',
            'status' => 'potential',
            'due_date' => today()->addDays(3)->toDateString(),
            'amount' => 8500000,
        ])
        ->assertRedirect(route('marketing.dashboard'));

    $plan = MarketingCashFlow::query()->firstOrFail();

    expect($plan->created_by)->toBe($user->id)
        ->and($plan->direction)->toBe('income')
        ->and((float) $plan->amount)->toBe(8500000.0);

    $this->actingAs($user)
        ->get(route('marketing.dashboard'))
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('rejects invalid cash flow plans without storing them', function () {
    $user = marketingCashFlowUser();

    $this->actingAs($user)
        ->post(route('marketing.cash-flows.store'), [
            'title' => '',
            'direction' => 'transfer',
            'status' => 'unknown',
            'due_date' => today()->subDay()->toDateString(),
            'amount' => 0,
        ])
        ->assertSessionHasErrors(['title', 'direction', 'status', 'due_date', 'amount']);

    $this->actingAs($user)
        ->post(route('marketing.cash-flows.store'), [
            'title' => 'Rencana di luar periode',
            'direction' => 'income',
            'status' => 'confirmed',
            'due_date' => today()->addDays(31)->toDateString(),
            'amount' => 1000,
        ])
        ->assertSessionHasErrors('due_date');

    $this->assertDatabaseCount('marketing_cash_flows', 0);
});

it('forbids users outside marketing from changing its cash balances', function () {
    $user = marketingCashFlowUser('staff_pembangunan', 'pembangunan');
    $account = MarketingCashAccount::query()->where('account_key', 'bank_bsn')->firstOrFail();

    $this->actingAs($user)
        ->put(route('marketing.cash-accounts.update', $account), ['balance' => 500])
        ->assertForbidden();

    expect($account->fresh()->balance)->toBeNull();
});

it('allows marketing users to remove an incorrect cash flow plan', function () {
    $user = marketingCashFlowUser();
    $plan = MarketingCashFlow::query()->create([
        'title' => 'Rencana keliru',
        'direction' => 'outcome',
        'status' => 'confirmed',
        'due_date' => today()->addDays(2),
        'amount' => 250000,
    ]);

    $this->actingAs($user)
        ->delete(route('marketing.cash-flows.destroy', $plan))
        ->assertRedirect(route('marketing.dashboard'));

    $this->assertDatabaseMissing('marketing_cash_flows', ['id' => $plan->id]);
});
