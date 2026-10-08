<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $pembangunan = Division::where(
            'slug',
            'pembangunan'
        )->firstOrFail();

        $marketing = Division::where(
            'slug',
            'marketing'
        )->firstOrFail();

        $pemberkasan = Division::where(
            'slug',
            'pemberkasan'
        )->firstOrFail();

        // ADMIN
        User::updateOrCreate(
            ['email' => 'admin@mulia-property.test'],
            [
                'name' => 'Admin Mulia Property',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'division_id' => null,
                'email_verified_at' => now(),
            ]
        );

        // PEMBANGUNAN
        User::updateOrCreate(
            ['email' => 'tl.pembangunan@mulia-property.test'],
            [
                'name' => 'TL Pembangunan',
                'password' => Hash::make('password'),
                'role' => 'tl_pembangunan',
                'division_id' => $pembangunan->id,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff.pembangunan@mulia-property.test'],
            [
                'name' => 'Staff Pembangunan',
                'password' => Hash::make('password'),
                'role' => 'staff_pembangunan',
                'division_id' => $pembangunan->id,
                'email_verified_at' => now(),
            ]
        );

        // MARKETING
        User::updateOrCreate(
            ['email' => 'tl.marketing@mulia-property.test'],
            [
                'name' => 'TL Marketing',
                'password' => Hash::make('password'),
                'role' => 'tl_marketing',
                'division_id' => $marketing->id,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff.marketing@mulia-property.test'],
            [
                'name' => 'Staff Marketing',
                'password' => Hash::make('password'),
                'role' => 'staff_marketing',
                'division_id' => $marketing->id,
                'email_verified_at' => now(),
            ]
        );

        // PEMBERKASAN
        User::updateOrCreate(
            ['email' => 'tl.pemberkasan@mulia-property.test'],
            [
                'name' => 'TL Pemberkasan',
                'password' => Hash::make('password'),
                'role' => 'tl_pemberkasan',
                'division_id' => $pemberkasan->id,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff.pemberkasan@mulia-property.test'],
            [
                'name' => 'Staff Pemberkasan',
                'password' => Hash::make('password'),
                'role' => 'staff_pemberkasan',
                'division_id' => $pemberkasan->id,
                'email_verified_at' => now(),
            ]
        );
    }
}
