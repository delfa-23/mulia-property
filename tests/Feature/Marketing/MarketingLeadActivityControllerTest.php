<?php

use App\Models\Block;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Division;
use App\Models\Lot;
use App\Models\MarketingLead;
use App\Models\MarketingLeadFollowUp;
use App\Models\MarketingLeadFollowUpHistory;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Carbon;

it('records call and survey activity against the selected lead', function () {
    $this->travelTo(Carbon::parse('2026-10-14 10:00:00'));
    $user = createCrmActivityTestUser();
    $lead = MarketingLead::factory()->create([
        'assigned_user_id' => $user->id,
        'name' => 'Lead Kalender CRM',
    ]);
    $property = Property::create(['name' => 'Perumahan CRM']);

    $this->actingAs($user)
        ->post(route('marketing.leads.calls.store', $lead), [
            'called_at' => '2026-10-14T09:30',
            'outcome' => 'answered',
            'duration_minutes' => 12,
            'notes' => 'Tertarik melihat rumah.',
            'next_follow_up_at' => '2026-10-16T09:00',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('marketing_lead_calls', [
        'lead_id' => $lead->id,
        'performed_by' => $user->id,
        'outcome' => 'answered',
        'duration_minutes' => 12,
        'notes' => 'Tertarik melihat rumah.',
    ]);

    $this->post(route('marketing.leads.surveys.store', $lead), [
        'property_id' => $property->id,
        'scheduled_at' => '2026-10-16T13:00',
        'status' => 'scheduled',
        'result_notes' => 'Calon pembeli akan datang bersama keluarga.',
    ])
        ->assertRedirect();

    $this->assertDatabaseHas('marketing_lead_surveys', [
        'lead_id' => $lead->id,
        'property_id' => $property->id,
        'assigned_user_id' => $user->id,
        'status' => 'scheduled',
    ]);

    $this->get(route('marketing.leads.show', $lead))
        ->assertSee('Riwayat panggilan')
        ->assertSee('Tertarik melihat rumah.')
        ->assertSee('Riwayat survei')
        ->assertSee('Perumahan CRM');

    $this->get(route('marketing.calendar', ['month' => '2026-10']))
        ->assertSee('Lead Kalender CRM')
        ->assertSee('Survei: Perumahan CRM');
});

it('stores follow-up tasks and records each status transition', function () {
    $this->travelTo(Carbon::parse('2026-10-14 10:00:00'));
    $user = createCrmActivityTestUser();
    $lead = MarketingLead::factory()->create(['assigned_user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('marketing.leads.follow-ups.store', $lead), [
            'title' => 'Hubungi calon pembeli',
            'description' => 'Konfirmasi jadwal kunjungan.',
            'due_at' => '2026-10-15T10:00',
            'priority' => 'high',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Tugas follow-up berhasil dibuat.');

    $followUp = MarketingLeadFollowUp::query()->sole();
    $this->assertDatabaseHas('marketing_lead_follow_up_histories', [
        'follow_up_id' => $followUp->id,
        'from_status' => null,
        'to_status' => 'pending',
    ]);

    $this->put(route('marketing.leads.follow-ups.update', [$lead, $followUp]), [
        'status' => 'completed',
        'result_notes' => 'Jadwal survei sudah disepakati.',
    ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Follow-up berhasil diperbarui.');

    $this->assertDatabaseHas('marketing_lead_follow_ups', [
        'id' => $followUp->id,
        'status' => 'completed',
        'result_notes' => 'Jadwal survei sudah disepakati.',
    ]);
    expect($followUp->fresh()->completed_at)->not->toBeNull();
    expect(MarketingLeadFollowUpHistory::query()->where('follow_up_id', $followUp->id)->count())->toBe(2);
});

it('prevents a marketing user from viewing or logging activity for another assigned lead', function () {
    $user = createCrmActivityTestUser();
    $otherUser = createCrmActivityTestUser();
    $lead = MarketingLead::factory()->create(['assigned_user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('marketing.leads.show', $lead))
        ->assertForbidden();

    $this->post(route('marketing.leads.calls.store', $lead), [
        'called_at' => now()->subMinute()->format('Y-m-d\TH:i'),
        'outcome' => 'answered',
    ])->assertForbidden();

    $this->assertDatabaseCount('marketing_lead_calls', 0);
});

it('counts unique leads rather than duplicate bookings in CRM conversion rates', function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    $user = createCrmActivityTestUser();
    $property = Property::create(['name' => 'Perumahan Laporan']);
    $leadWithTwoBookings = MarketingLead::factory()->create([
        'assigned_user_id' => $user->id,
        'date_received' => '2026-10-14',
    ]);
    $leadWithoutBooking = MarketingLead::factory()->create([
        'assigned_user_id' => $user->id,
        'date_received' => '2026-10-15',
    ]);
    createCrmActivityTestBooking($leadWithTwoBookings, $property, 'akad', '01');
    createCrmActivityTestBooking($leadWithTwoBookings, $property, 'finish', '02');

    $response = $this->actingAs($user)
        ->get(route('marketing.reports.crm', ['from' => '2026-10-14', 'to' => '2026-10-15']))
        ->assertSee('Rumus metrik');

    expect($response->viewData('metrics'))->toMatchArray([
        'leads' => 2,
        'bookings' => 2,
        'akad_bookings' => 2,
        'booked_leads' => 1,
        'akad_leads' => 1,
        'lead_to_booking_rate' => 50.0,
        'lead_to_akad_rate' => 50.0,
    ]);

    $this->assertModelExists($leadWithoutBooking);
});

function createCrmActivityTestUser(): User
{
    $division = Division::firstOrCreate(
        ['slug' => 'marketing'],
        ['name' => 'Marketing', 'is_active' => true],
    );

    return User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
}

function createCrmActivityTestBooking(MarketingLead $lead, Property $property, string $status, string $lotNumber): Booking
{
    $block = Block::firstOrCreate(
        ['property_id' => $property->id, 'name' => 'A'],
    );
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => $lotNumber,
        'house_price' => 350000000,
    ]);
    $customer = Customer::create(['name' => 'CRM '.$lotNumber]);

    return Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'lead_id' => $lead->id,
        'booking_date' => '2026-10-15',
        'status' => $status,
    ]);
}
