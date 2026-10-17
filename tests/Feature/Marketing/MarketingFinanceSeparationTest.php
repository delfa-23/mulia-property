<?php

use App\Models\Division;
use App\Models\User;

it('keeps finance balances and plans out of the marketing dashboard', function () {
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
        ->get(route('marketing.dashboard'))
        ->assertDontSee('Saldo Kas &amp; Bank Marketing')
        ->assertDontSee('Rencana Income')
        ->assertDontSee('Tambah Rencana Income / Outcome');
});

it('does not expose finance cash actions under marketing routes', function () {
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
        ->post('/marketing/cash-flows')
        ->assertNotFound();
});
