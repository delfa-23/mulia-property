<?php

use App\Models\Customer;
use App\Models\Division;
use App\Models\User;

it('renders customer create and edit forms without file uploads', function () {
    $user = createMarketingCustomerControllerTestUser();
    $customer = Customer::create(['name' => 'Customer Test']);

    $this->actingAs($user)
        ->get(route('marketing.customers.create'))
        ->assertSee('Dokumen tidak wajib pada form ini.')
        ->assertDontSee('type="file"', false)
        ->assertDontSee('enctype="multipart/form-data"', false);

    $this->actingAs($user)
        ->get(route('marketing.customers.edit', $customer))
        ->assertSee('Dokumen tidak wajib pada form ini.')
        ->assertDontSee('type="file"', false)
        ->assertDontSee('enctype="multipart/form-data"', false);
});

it('creates a customer from biodata without attached documents', function () {
    $user = createMarketingCustomerControllerTestUser();

    $this->actingAs($user)
        ->post(route('marketing.customers.store'), [
            'name' => 'Customer Baru',
            'birth_place' => 'Bandung',
            'birth_date' => '1990-01-02',
            'nik' => '1234567890123456',
            'marital_status' => 'Menikah',
            'occupation' => 'Wiraswasta',
            'phone' => '081234567890',
            'email' => 'customer@example.com',
            'address' => 'Jl. Contoh No. 1',
        ])
        ->assertRedirect(route('marketing.customers.index'))
        ->assertSessionHas('success', 'Customer berhasil ditambahkan.');

    $this->assertDatabaseHas('customers', [
        'name' => 'Customer Baru',
        'nik' => '1234567890123456',
        'email' => 'customer@example.com',
        'ktp_file' => null,
        'kk_file' => null,
        'npwp_file' => null,
        'booking_form_file' => null,
    ]);
});

it('preserves existing document references when updating customer biodata', function () {
    $user = createMarketingCustomerControllerTestUser();
    $customer = Customer::create([
        'name' => 'Customer Lama',
        'ktp_file' => 'customers/documents/legacy-ktp.pdf',
    ]);

    $this->actingAs($user)
        ->put(route('marketing.customers.update', $customer), [
            'name' => 'Customer Diperbarui',
        ])
        ->assertRedirect(route('marketing.customers.index'))
        ->assertSessionHas('success', 'Customer berhasil diperbarui.');

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => 'Customer Diperbarui',
        'ktp_file' => 'customers/documents/legacy-ktp.pdf',
    ]);
});

function createMarketingCustomerControllerTestUser(): User
{
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);

    return $user;
}
