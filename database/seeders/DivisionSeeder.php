<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        Division::create([
            'name' => 'Pembangunan',
            'slug' => 'pembangunan',
            'description' => 'Divisi yang menangani perkembangan fisik proyek.',
        ]);

        Division::create([
            'name' => 'Marketing',
            'slug' => 'marketing',
            'description' => 'Divisi yang menangani penjualan dan kontrol kavling.',
        ]);

        Division::create([
            'name' => 'Pemberkasan',
            'slug' => 'pemberkasan',
            'description' => 'Divisi yang menangani proses berkas sampai akad.',
        ]);
    }
}
