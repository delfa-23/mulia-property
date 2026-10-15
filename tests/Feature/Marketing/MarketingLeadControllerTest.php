<?php

use App\Models\Block;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Division;
use App\Models\Lot;
use App\Models\MarketingLead;
use App\Models\Property;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Support\Carbon;

it('records a lead with its marketing category, source, and owner', function () {
    $user = createMarketingLeadFeatureTestUser();
    $sales = Sales::create(['name' => 'Sales Prospek']);

    $this->actingAs($user)
        ->post(route('marketing.leads.store'), [
            'name' => 'Calon Pembeli',
            'date_received' => '2026-10-14',
            'phone' => '081234567890',
            'domicile' => 'Perbaungan',
            'sales_id' => $sales->id,
            'category' => 'Hot',
            'source' => 'Meta Ads',
            'last_contact_at' => '2026-10-14',
            'last_progress' => 'Meminta jadwal survei.',
        ])
        ->assertRedirect(route('marketing.leads.index'))
        ->assertSessionHas('success', 'Prospek berhasil ditambahkan.');

    $this->assertDatabaseHas('marketing_leads', [
        'name' => 'Calon Pembeli',
        'date_received' => '2026-10-14',
        'phone' => '081234567890',
        'domicile' => 'Perbaungan',
        'sales_id' => $sales->id,
        'category' => 'Hot',
        'source' => 'Meta Ads',
        'last_progress' => 'Meminta jadwal survei.',
    ]);
});

it('accepts the Cool category for leads that did not respond', function () {
    $user = createMarketingLeadFeatureTestUser();

    $this->actingAs($user)
        ->post(route('marketing.leads.store'), [
            'name' => 'Prospek Tidak Merespons',
            'date_received' => '2026-10-14',
            'category' => 'Cool',
            'source' => 'Meta Ads',
        ])
        ->assertRedirect(route('marketing.leads.index'));

    $this->assertDatabaseHas('marketing_leads', [
        'name' => 'Prospek Tidak Merespons',
        'category' => 'Cool',
    ]);
});

it('rejects categories and sources that are not in the marketing options', function () {
    $user = createMarketingLeadFeatureTestUser();

    $this->actingAs($user)
        ->post(route('marketing.leads.store'), [
            'name' => 'Prospek Tidak Valid',
            'date_received' => '2026-10-14',
            'category' => 'Siap Beli',
            'source' => 'Unknown',
        ])
        ->assertSessionHasErrors(['category', 'source']);

    $this->assertDatabaseCount('marketing_leads', 0);
});

it('counts only leads linked to bookings at akad or finished status', function () {
    $user = createMarketingLeadFeatureTestUser();
    $akadLead = MarketingLead::factory()->create([
        'name' => 'Lead Akad',
        'category' => 'Hot',
        'source' => 'Rekomendasi',
    ]);
    $finishedLead = MarketingLead::factory()->create([
        'name' => 'Lead Selesai',
        'category' => 'Warm',
        'source' => 'Tiktok',
    ]);
    $processLead = MarketingLead::factory()->create([
        'name' => 'Lead Proses',
        'category' => 'Cool',
        'source' => 'Marketplace',
        'last_progress' => '<script>alert("x")</script>',
    ]);

    createMarketingLeadFeatureTestBooking($akadLead, 'akad');
    createMarketingLeadFeatureTestBooking($finishedLead, 'finish');
    createMarketingLeadFeatureTestBooking($processLead, 'process');

    $this->actingAs($user)
        ->get(route('marketing.leads.index'))
        ->assertSee('Total Prospek')
        ->assertSee('>3</p>', false)
        ->assertSee('Kategori Hot')
        ->assertSee('>1</p>', false)
        ->assertSee('Sampai Akad')
        ->assertSee('Lead Akad')
        ->assertSee('Lead Selesai')
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert("x")</script>', false);
});

it('links a prospect when a booking is created from the marketing form', function () {
    $this->travelTo(Carbon::parse('2026-10-12 10:00:00'));
    $user = createMarketingLeadFeatureTestUser();
    $property = Property::create(['name' => 'Perumahan Prospek']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $customer = Customer::create(['name' => 'Customer Prospek']);
    $lead = MarketingLead::factory()->create(['name' => 'Lead Menjadi Booking']);

    $this->actingAs($user)
        ->get(route('marketing.bookings.create'))
        ->assertSee('name="lead_id"', false)
        ->assertSee('Lead Menjadi Booking');

    $this->post(route('marketing.bookings.store'), [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_mode' => 'existing',
        'customer_id' => $customer->id,
        'lead_id' => $lead->id,
        'booking_date' => '2026-10-12',
        'blocking_until' => '2026-10-20 10:00:00',
        'akad_fee' => 0,
        'status' => 'booking',
    ])
        ->assertRedirect(route('marketing.bookings.index'))
        ->assertSessionHas('success', 'Booking berhasil dibuat.');

    $this->assertDatabaseHas('bookings', [
        'customer_id' => $customer->id,
        'lead_id' => $lead->id,
        'status' => 'booking',
    ]);
});

it('archives a lead record without removing it when it is already associated with a booking', function () {
    $user = createMarketingLeadFeatureTestUser();
    $lead = MarketingLead::factory()->create();
    createMarketingLeadFeatureTestBooking($lead, 'process');

    $this->actingAs($user)
        ->delete(route('marketing.leads.destroy', $lead))
        ->assertRedirect(route('marketing.leads.index'))
        ->assertSessionHas('success', 'Prospek berhasil diarsipkan.');

    $this->assertModelExists($lead);
    $this->assertNotNull($lead->fresh()->archived_at);
});

it('forbids users outside marketing from accessing lead records', function () {
    $division = Division::create([
        'name' => 'Pembangunan',
        'slug' => 'pembangunan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->get(route('marketing.leads.index'))
        ->assertForbidden();
});

function createMarketingLeadFeatureTestUser(): User
{
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);

    return User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
}

function createMarketingLeadFeatureTestBooking(MarketingLead $lead, string $status): Booking
{
    $property = Property::create(['name' => 'Perumahan untuk '.$lead->name]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => (string) $lead->id,
        'house_price' => 350000000,
    ]);
    $customer = Customer::create(['name' => 'Customer '.$lead->name]);

    return Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'lead_id' => $lead->id,
        'booking_date' => '2026-10-12',
        'status' => $status,
    ]);
}
