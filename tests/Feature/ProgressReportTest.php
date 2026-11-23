<?php

use App\Exports\ProgressReportsByDivisionExport;
use App\Models\Block;
use App\Models\Division;
use App\Models\Lot;
use App\Models\ProgressReport;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

test('admin sees submitted and approved progress reports grouped by division with their details', function () {
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
            'report_date' => $createdAt->toDateString(),
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
        ->assertSee('Laporan 3')
        ->assertSee('Laporan 16')
        ->assertSee('Approve')
        ->assertDontSee('Progress (%)')
        ->assertDontSee('5.00%');
});

test('admin sees one report date without a reporting period range', function () {
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
        'report_date' => '2026-10-05',
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-05',
        'title' => 'Laporan Periode Terlewati',
        'status' => 'approved',
    ]);
    ProgressReport::create([
        'division_id' => $division->id,
        'created_by' => $admin->id,
        'report_date' => '2026-10-06',
        'period_start' => '2026-10-06',
        'period_end' => '2026-10-07',
        'title' => 'Laporan Periode Mendatang',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($admin)->get(route('reports.progress.index'));

    $response->assertSee('Tanggal Laporan')
        ->assertSee('05/10/2026')
        ->assertSee('06/10/2026')
        ->assertDontSee('Periode laporan')
        ->assertDontSee('Periode Mulai')
        ->assertDontSee('Periode Selesai');
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
        'report_date' => '2026-09-01',
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
        'report_date' => '2026-09-08',
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
            && $row[4] === 'Laporan Penjualan Mingguan'
            && $row[5] === '01/09/2026'
            && $row[6] === 'Menyelesaikan tindak lanjut calon pembeli.')
            && ! $rows->contains(fn ($row) => $row[4] === 'Laporan Menunggu')
            && $rows->contains(fn ($row) => $row[0] === 'Divisi Pemberkasan'
                && $row[3] === 0
                && $row[4] === null);
    });

    expect((new ProgressReportsByDivisionExport)->headings())
        ->toContain('Tanggal Laporan', 'Isi Laporan')
        ->not->toContain('Periode Mulai')
        ->not->toContain('Periode Selesai', 'Rata-rata Progress (%)', 'Progress (%)');
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

test('construction staff must choose a matching property and lot and capture a photo', function () {
    Storage::fake(ProgressReport::PUBLIC_PHOTO_DISK);
    $division = Division::create([
        'name' => 'Divisi Pembangunan',
        'slug' => 'pembangunan',
        'is_active' => true,
    ]);
    $staff = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan A']);
    $otherProperty = Property::create(['name' => 'Perumahan B']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'available',
    ]);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    $otherLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '02',
        'house_price' => 350000000,
        'status' => 'available',
    ]);

    $this->actingAs($staff)
        ->get(route('reports.progress.create'))
        ->assertOk()
        ->assertSee('Tanggal Pembuatan Laporan')
        ->assertSee('name="report_date"', false)
        ->assertDontSee('name="period_start"', false)
        ->assertDontSee('name="period_end"', false)
        ->assertSee('name="content"', false)
        ->assertDontSee('name="progress"', false)
        ->assertDontSee('Progress (%)')
        ->assertSee('data-camera-start', false)
        ->assertSee('data-property-select', false)
        ->assertSee('data-lot-select', false)
        ->assertDontSee('Pilih foto dari galeri');

    $reportData = [
        'report_date' => '2026-11-20',
        'title' => 'Progress Pondasi',
        'content' => 'Pekerjaan pondasi berjalan.',
        'progress' => 50,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
    ];

    $this->from(route('reports.progress.create'))
        ->post(route('reports.progress.store'), $reportData)
        ->assertSessionHasErrors('photo');

    $this->from(route('reports.progress.create'))
        ->post(route('reports.progress.store'), [
            ...$reportData,
            'report_date' => null,
            'photo' => UploadedFile::fake()->image('lapangan.jpg'),
        ])
        ->assertSessionHasErrors('report_date');

    $this->from(route('reports.progress.create'))
        ->post(route('reports.progress.store'), [
            ...$reportData,
            'lot_id' => $otherLot->id,
            'photo' => UploadedFile::fake()->image('lapangan.jpg'),
        ])
        ->assertSessionHasErrors('lot_id');

    $this->post(route('reports.progress.store'), [
        ...$reportData,
        'photo' => UploadedFile::fake()->image('lapangan.jpg'),
    ])->assertRedirect(route('reports.progress.index'));

    $report = ProgressReport::query()->latest('id')->firstOrFail();

    $this->assertDatabaseHas('progress_reports', [
        'id' => $report->id,
        'division_id' => $division->id,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'created_by' => $staff->id,
        'status' => 'draft',
    ]);
    expect($report->report_date->toDateString())->toBe('2026-11-20');
    expect($report->progress)->toBeNull();
    Storage::disk(ProgressReport::PUBLIC_PHOTO_DISK)->assertExists($report->photo_path);
    expect($report->photo_path)->toStartWith(ProgressReport::PUBLIC_PHOTO_DIRECTORY.'/');
    $this->get(route('reports.progress.photo', $report))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
    $previousPhotoPath = $report->photo_path;

    $this->actingAs($staff)
        ->get(route('reports.progress.edit', $report))
        ->assertOk()
        ->assertSee('name="report_date"', false)
        ->assertDontSee('name="period_start"', false)
        ->assertDontSee('name="period_end"', false)
        ->assertDontSee('name="progress"', false)
        ->assertDontSee('Progress (%)');

    $this->put(route('reports.progress.update', $report), [
        'report_date' => '2026-11-21',
        'title' => 'Progress Pondasi Diperbarui',
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'photo' => UploadedFile::fake()->image('lapangan-baru.jpg'),
    ])->assertRedirect(route('reports.progress.index'));

    $report->refresh();
    expect($report->report_date->toDateString())->toBe('2026-11-21');
    $this->assertSame('2026-11-21', $report->period_start->toDateString());
    $this->assertSame('2026-11-21', $report->period_end->toDateString());
    expect($report->photo_path)->not->toBe($previousPhotoPath)
        ->and($report->photoUrl())->toBe(asset($report->photo_path));
    Storage::disk(ProgressReport::PUBLIC_PHOTO_DISK)->assertMissing($previousPhotoPath);
    Storage::disk(ProgressReport::PUBLIC_PHOTO_DISK)->assertExists($report->photo_path);
    $this->get(route('reports.progress.index'))
        ->assertSee(asset($report->photo_path), false);
});

test('construction team lead can create a report but cannot approve construction reports', function () {
    Storage::fake(ProgressReport::PUBLIC_PHOTO_DISK);
    $division = Division::create([
        'name' => 'Divisi Pembangunan',
        'slug' => 'pembangunan',
        'is_active' => true,
    ]);
    $teamLead = User::factory()->create([
        'role' => 'tl_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan TL']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'available',
    ]);

    $this->actingAs($teamLead)
        ->get(route('reports.progress.create'))
        ->assertOk();

    $this->post(route('reports.progress.store'), [
        'report_date' => '2026-11-20',
        'title' => 'Menunggu Admin',
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'photo' => UploadedFile::fake()->image('lapangan.jpg'),
    ])->assertRedirect(route('reports.progress.index'));
    $report = ProgressReport::query()->latest('id')->firstOrFail();
    $this->post(route('reports.progress.submit', $report))
        ->assertSessionHas('success', 'Laporan berhasil dikirim untuk review admin.');

    $this->post(route('reports.progress.review', $report), [
        'action' => 'approved',
    ])->assertForbidden();

    $this->assertDatabaseHas('progress_reports', [
        'id' => $report->id,
        'status' => 'submitted',
    ]);
});

test('admin approval replaces the current lot photo and archives the previous photo for admins only', function () {
    Storage::fake('local');
    Storage::fake(ProgressReport::PUBLIC_PHOTO_DISK);
    $division = Division::create([
        'name' => 'Divisi Pembangunan',
        'slug' => 'pembangunan',
        'is_active' => true,
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Arsip']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'available',
    ]);
    $oldPhotoPath = 'progress-reports/old.jpg';
    $newPhotoPath = 'progress-reports/new.jpg';
    Storage::disk('local')->put($oldPhotoPath, 'old photo');
    Storage::disk('local')->put($newPhotoPath, 'new photo');
    $oldReport = ProgressReport::create([
        'division_id' => $division->id,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'created_by' => $staff->id,
        'report_date' => '2026-11-13',
        'period_start' => '2026-11-13',
        'period_end' => '2026-11-19',
        'title' => 'Foto Sebelumnya',
        'photo_path' => $oldPhotoPath,
        'status' => 'approved',
    ]);
    $newReport = ProgressReport::create([
        'division_id' => $division->id,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'created_by' => $staff->id,
        'report_date' => '2026-11-20',
        'period_start' => '2026-11-20',
        'period_end' => '2026-11-26',
        'title' => 'Foto Terbaru',
        'photo_path' => $newPhotoPath,
        'status' => 'submitted',
    ]);

    $this->actingAs($admin)
        ->from(route('reports.progress.index'))
        ->post(route('reports.progress.review', $newReport), [
            'action' => 'approved',
        ])
        ->assertRedirect(route('reports.progress.index'));

    $this->assertDatabaseHas('progress_reports', [
        'id' => $oldReport->id,
        'status' => 'approved',
    ]);
    expect($oldReport->fresh()->photo_archived_at)->not->toBeNull()
        ->and($newReport->fresh()->photo_archived_at)->toBeNull()
        ->and($lot->fresh()->currentProgressReport->is($newReport->fresh()))->toBeTrue();
    Storage::disk('local')->assertExists($oldPhotoPath);

    $this->get(route('reports.progress.archive'))
        ->assertOk()
        ->assertSee('Foto Sebelumnya')
        ->assertSee(route('reports.progress.photo', $oldReport), false);
    $this->get(route('reports.progress.photo', $oldReport))
        ->assertRedirect();
    $oldReport->refresh();
    expect($oldReport->photo_path)
        ->toStartWith(ProgressReport::PUBLIC_PHOTO_DIRECTORY.'/');
    Storage::disk(ProgressReport::PUBLIC_PHOTO_DISK)
        ->assertExists($oldReport->photo_path);

    $this->actingAs($staff)
        ->get(route('reports.progress.archive'))
        ->assertForbidden();

    $this->get(route('reports.progress.photo', $oldReport))
        ->assertForbidden();

    $this->actingAs($staff)
        ->get(route('reports.progress.photo', $newReport))
        ->assertRedirect();
    $newReport->refresh();

    $this->get(route('pembangunan.properties.index'))
        ->assertSee(asset($newReport->photo_path), false)
        ->assertDontSee(asset($oldReport->photo_path), false);
});
