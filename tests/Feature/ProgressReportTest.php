<?php

use App\Exports\ProgressReportsByDivisionExport;
use App\Models\Division;
use App\Models\ProgressReport;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;

test('admin sees only approved progress reports grouped by division with their details', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $marketing = Division::create([
        'name' => 'Divisi Marketing',
        'slug' => 'divisi-marketing',
        'is_active' => true,
    ]);
    $construction = Division::create([
        'name' => 'Divisi Pembangunan',
        'slug' => 'divisi-pembangunan',
        'is_active' => true,
    ]);

    foreach (range(1, 16) as $number) {
        $createdAt = now()->subDays(16 - $number);

        ProgressReport::create([
            'division_id' => $number % 2 === 0 ? $construction->id : $marketing->id,
            'created_by' => $admin->id,
            'period_start' => $createdAt->toDateString(),
            'period_end' => $createdAt->copy()->addDays(6)->toDateString(),
            'title' => "Laporan {$number}",
            'content' => "Rincian laporan {$number}",
            'progress' => $number * 5,
            'status' => $number <= 2 ? 'approved' : 'submitted',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    $response = $this->actingAs($admin)->get(route('reports.progress.index'));

    $response->assertSee('Divisi Marketing')
        ->assertSee('Divisi Pembangunan')
        ->assertSee('Laporan 1')
        ->assertSee('Rincian laporan 1')
        ->assertSee('Laporan 2')
        ->assertSee('Rincian laporan 2')
        ->assertDontSee('Laporan 3')
        ->assertDontSee('Laporan 16');
});

test('admin sees an alert when an approved progress report period has ended', function () {
    $this->travelTo(now()->setDate(2026, 10, 6)->setTime(12, 0));
    $admin = User::factory()->create(['role' => 'admin']);
    $division = Division::create([
        'name' => 'Divisi Pembangunan',
        'slug' => 'divisi-pembangunan',
        'is_active' => true,
    ]);
    ProgressReport::create([
        'division_id' => $division->id,
        'created_by' => $admin->id,
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-05',
        'title' => 'Laporan Periode Terlewati',
        'status' => 'approved',
    ]);
    ProgressReport::create([
        'division_id' => $division->id,
        'created_by' => $admin->id,
        'period_start' => '2026-10-06',
        'period_end' => '2026-10-07',
        'title' => 'Laporan Periode Mendatang',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($admin)->get(route('reports.progress.index'));

    $response->assertSee('Periode laporan Laporan Periode Terlewati telah berakhir')
        ->assertDontSee('Laporan Periode Mendatang telah berakhir');
});

test('admin can download an Excel report containing each division and its progress details', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $marketing = Division::create([
        'name' => 'Divisi Marketing',
        'slug' => 'divisi-marketing',
        'description' => 'Pemasaran project',
        'is_active' => true,
    ]);
    Division::create([
        'name' => 'Divisi Pemberkasan',
        'slug' => 'divisi-pemberkasan',
        'is_active' => true,
    ]);
    ProgressReport::create([
        'division_id' => $marketing->id,
        'created_by' => $admin->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-07',
        'title' => 'Laporan Penjualan Mingguan',
        'content' => 'Menyelesaikan tindak lanjut calon pembeli.',
        'progress' => 67.5,
        'status' => 'approved',
    ]);
    ProgressReport::create([
        'division_id' => $marketing->id,
        'created_by' => $admin->id,
        'period_start' => '2026-09-08',
        'period_end' => '2026-09-14',
        'title' => 'Laporan Menunggu',
        'status' => 'submitted',
    ]);

    Excel::fake();

    $response = $this->actingAs($admin)->get(route('reports.progress.export'));

    $response->assertOk();
    Excel::matchByRegex();
    Excel::assertDownloaded('/^progress-seluruh-divisi-\d{8}-\d{6}\.xlsx$/', function ($export): bool {
        if (! $export instanceof ProgressReportsByDivisionExport) {
            return false;
        }

        $rows = $export->collection();

        return $rows->contains(fn ($row) => $row[0] === 'Divisi Marketing'
            && $row[5] === 'Laporan Penjualan Mingguan'
            && $row[8] === 'Menyelesaikan tindak lanjut calon pembeli.'
            && $row[10] === 67.5)
            && ! $rows->contains(fn ($row) => $row[5] === 'Laporan Menunggu')
            && $rows->contains(fn ($row) => $row[0] === 'Divisi Pemberkasan'
                && $row[3] === 0
                && $row[5] === null);
    });
});

test('non-admin cannot download progress reports across divisions', function () {
    $division = Division::create([
        'name' => 'Divisi Marketing',
        'slug' => 'divisi-marketing',
        'is_active' => true,
    ]);
    $staff = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);

    $response = $this->actingAs($staff)->get(route('reports.progress.export'));

    $response->assertForbidden();
});
