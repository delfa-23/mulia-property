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

it('shows CRM reports for all marketing accounts to staff users', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $staff = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $otherStaff = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
        'name' => 'Marketing Lain',
    ]);
    MarketingLead::create([
        'name' => 'Prospek Marketing Lain',
        'date_received' => '2026-10-10',
        'assigned_user_id' => $otherStaff->id,
        'created_by' => $otherStaff->id,
        'category' => 'Hot',
        'source' => 'Instagram / FB',
    ]);

    $this->actingAs($staff)
        ->get(route('marketing.reports.crm', [
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]))
        ->assertSee('Marketing Lain')
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['leads'] === 1);
});

it('records a lead with its marketing category, source, and owner', function () {
    $user = createMarketingLeadFeatureTestUser();
    $sales = Sales::create(['name' => 'Sales Prospek']);
    $property = Property::create(['name' => 'Perumahan Prospek']);

    $this->actingAs($user)
        ->post(route('marketing.leads.store'), [
            'name' => 'Calon Pembeli',
            'date_received' => '2026-10-14',
            'phone' => '081234567890',
            'domicile' => 'Perbaungan',
            'property_id' => $property->id,
            'sales_id' => $sales->id,
            'category' => 'Hot',
            'source' => 'Meta Ads',
            'last_contact_at' => '2026-10-14',
            'last_progress' => 'Meminta jadwal survei.',
        ])
        ->assertRedirect(route('marketing.leads.index'))
        ->assertSessionHas('success', 'Prospek berhasil ditambahkan.');

    $lead = MarketingLead::query()->where('name', 'Calon Pembeli')->firstOrFail();
    $this->get(route('marketing.leads.show', $lead))
        ->assertSee('Perumahan Prospek');

    $this->assertDatabaseHas('marketing_leads', [
        'name' => 'Calon Pembeli',
        'date_received' => '2026-10-14 00:00:00',
        'phone' => '081234567890',
        'domicile' => 'Perbaungan',
        'property_id' => $property->id,
        'sales_id' => $sales->id,
        'category' => 'Hot',
        'source' => 'Meta Ads',
        'last_progress' => 'Meminta jadwal survei.',
        'created_by' => $user->id,
    ]);
});

it('offers properties on the prospect form and filters the list by property', function () {
    $user = createMarketingLeadFeatureTestUser();
    $property = Property::create(['name' => 'Perumahan Pilihan']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    MarketingLead::factory()->create([
        'name' => 'Lead Perumahan Pilihan',
        'property_id' => $property->id,
        'created_by' => $user->id,
    ]);
    MarketingLead::factory()->create([
        'name' => 'Lead Perumahan Lain',
        'property_id' => $otherProperty->id,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('marketing.leads.create'))
        ->assertSee('name="property_id"', false)
        ->assertSee('Perumahan Pilihan');

    $this->get(route('marketing.leads.index', ['property_id' => $property->id]))
        ->assertSee('Lead Perumahan Pilihan')
        ->assertSee('Perumahan Pilihan')
        ->assertDontSee('Lead Perumahan Lain');
});

it('keeps prospect data private to its creator while allowing admins to see all records', function () {
    $staff = createMarketingLeadFeatureTestUser();
    $otherStaff = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $staff->division_id,
    ]);
    $teamLead = User::factory()->create([
        'role' => 'tl_marketing',
        'division_id' => $staff->division_id,
    ]);
    $admin = User::factory()->create([
        'role' => 'admin',
        'division_id' => $staff->division_id,
    ]);
    $ownLead = MarketingLead::factory()->create([
        'name' => 'Prospek Akun Saya',
        'created_by' => $staff->id,
    ]);
    $otherLead = MarketingLead::factory()->create([
        'name' => 'Prospek Akun Lain',
        'created_by' => $otherStaff->id,
    ]);

    $this->actingAs($staff)
        ->get(route('marketing.leads.index'))
        ->assertSee('Prospek Akun Saya')
        ->assertDontSee('Prospek Akun Lain');

    $this->get(route('marketing.leads.show', $otherLead))->assertNotFound();
    $this->actingAs($teamLead)->get(route('marketing.leads.show', $otherLead))->assertNotFound();
    $this->actingAs($admin)->get(route('marketing.leads.show', $otherLead))->assertOk();

    $this->actingAs($staff)
        ->put(route('marketing.leads.update', $otherLead), [])
        ->assertNotFound();

    $this->assertModelExists($ownLead);
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

it('accepts Booking and Survey as prospect categories', function () {
    $user = createMarketingLeadFeatureTestUser();

    foreach (['Booking', 'Survey'] as $category) {
        $this->actingAs($user)
            ->post(route('marketing.leads.store'), [
                'name' => 'Prospek '.$category,
                'date_received' => '2026-10-14',
                'category' => $category,
                'source' => 'Meta Ads',
            ])
            ->assertRedirect(route('marketing.leads.index'));

        $this->assertDatabaseHas('marketing_leads', [
            'name' => 'Prospek '.$category,
            'category' => $category,
        ]);
    }
});

it('rejects invalid properties, categories, and sources when creating a lead', function () {
    $user = createMarketingLeadFeatureTestUser();

    $this->actingAs($user)
        ->post(route('marketing.leads.store'), [
            'name' => 'Prospek Tidak Valid',
            'date_received' => '2026-10-14',
            'property_id' => 999,
            'category' => 'Closing',
            'source' => 'Unknown',
        ])
        ->assertSessionHasErrors(['property_id', 'category', 'source']);

    $this->assertDatabaseCount('marketing_leads', 0);
});

it('omits standalone follow-up and calendar navigation from the prospect workspace', function () {
    $user = createMarketingLeadFeatureTestUser();

    $this->actingAs($user)
        ->get(route('marketing.leads.index'))
        ->assertDontSee('Follow-up CRM')
        ->assertDontSee('Kalender CRM')
        ->assertDontSee(route('marketing.follow-ups.index'), false)
        ->assertDontSee(route('marketing.calendar'), false);
});

it('counts only leads linked to bookings at akad or finished status', function () {
    $user = createMarketingLeadFeatureTestUser();
    $akadLead = MarketingLead::factory()->create([
        'name' => 'Lead Akad',
        'category' => 'Hot',
        'source' => 'Rekomendasi',
        'created_by' => $user->id,
    ]);
    $finishedLead = MarketingLead::factory()->create([
        'name' => 'Lead Selesai',
        'category' => 'Warm',
        'source' => 'Tiktok',
        'created_by' => $user->id,
    ]);
    $processLead = MarketingLead::factory()->create([
        'name' => 'Lead Proses',
        'category' => 'Cool',
        'source' => 'Marketplace',
        'last_progress' => '<script>alert("x")</script>',
        'created_by' => $user->id,
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
    $lead = MarketingLead::factory()->create([
        'name' => 'Lead Menjadi Booking',
        'created_by' => $user->id,
    ]);

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

it('archives an owned lead without removing it when it is already associated with a booking', function () {
    $user = createMarketingLeadFeatureTestUser();
    $lead = MarketingLead::factory()->create(['created_by' => $user->id]);
    createMarketingLeadFeatureTestBooking($lead, 'process');

    $this->actingAs($user)
        ->delete(route('marketing.leads.destroy', $lead))
        ->assertRedirect(route('marketing.leads.index'))
        ->assertSessionHas('success', 'Prospek berhasil diarsipkan.');

    $this->assertModelExists($lead);
    $this->assertNotNull($lead->fresh()->archived_at);
});

it('allows an admin to permanently delete a lead', function () {
    $admin = createMarketingLeadFeatureTestAdmin();
    $lead = MarketingLead::factory()->create();

    $this->actingAs($admin)
        ->delete(route('marketing.leads.force-destroy', $lead))
        ->assertRedirect(route('marketing.leads.index'))
        ->assertSessionHas('success', 'Prospek berhasil dihapus permanen.');

    $this->assertModelMissing($lead);
});

it('forbids non-admin users from permanently deleting a lead', function () {
    $user = createMarketingLeadFeatureTestUser();
    $lead = MarketingLead::factory()->create();

    $this->actingAs($user)
        ->delete(route('marketing.leads.force-destroy', $lead))
        ->assertForbidden();

    $this->assertModelExists($lead);
});

it('keeps a linked booking when an admin permanently deletes its lead', function () {
    $admin = createMarketingLeadFeatureTestAdmin();
    $lead = MarketingLead::factory()->create();
    $booking = createMarketingLeadFeatureTestBooking($lead, 'process');

    $this->actingAs($admin)
        ->delete(route('marketing.leads.force-destroy', $lead))
        ->assertRedirect(route('marketing.leads.index'));

    $this->assertModelMissing($lead);
    $this->assertNull($booking->fresh()->lead_id);
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

function createMarketingLeadFeatureTestAdmin(): User
{
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);

    return User::factory()->create([
        'role' => 'admin',
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
        'created_by' => $lead->created_by,
        'booking_date' => '2026-10-12',
        'status' => $status,
    ]);
}
