<?php

use App\Enums\TeamRole;
use App\Models\Division;
use App\Models\ProgressReport;
use App\Models\Team;
use App\Models\User;

test('staff and team leads can access routes in their assigned division', function (string $role, string $divisionSlug, string $routeName) {
    $division = Division::create([
        'name' => ucfirst($divisionSlug),
        'slug' => $divisionSlug,
        'is_active' => true,
    ]);
    $user = createDivisionAccessUser($role, $division->id);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'pembangunan staff' => ['staff_pembangunan', 'pembangunan', 'test.pembangunan'],
    'pembangunan team lead' => ['tl_pembangunan', 'pembangunan', 'test.pembangunan'],
    'marketing staff' => ['staff_marketing', 'marketing', 'test.marketing'],
    'marketing team lead' => ['tl_marketing', 'marketing', 'test.marketing'],
    'pemberkasan staff' => ['staff_pemberkasan', 'pemberkasan', 'test.pemberkasan'],
    'pemberkasan team lead' => ['tl_pemberkasan', 'pemberkasan', 'test.pemberkasan'],
]);

test('users cannot enter a division that conflicts with their role', function (string $role, string $divisionSlug, string $routeName) {
    $division = Division::create([
        'name' => ucfirst($divisionSlug),
        'slug' => $divisionSlug,
        'is_active' => true,
    ]);
    $user = createDivisionAccessUser($role, $division->id);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    'marketing role assigned to pembangunan' => ['staff_marketing', 'pembangunan', 'test.pembangunan'],
    'pembangunan role assigned to pemberkasan' => ['tl_pembangunan', 'pemberkasan', 'test.pemberkasan'],
    'pemberkasan role assigned to marketing' => ['staff_pemberkasan', 'marketing', 'test.marketing'],
]);

test('admin account creation assigns each role to its matching division', function (string $role, string $divisionSlug) {
    $division = Division::create([
        'name' => ucfirst($divisionSlug),
        'slug' => $divisionSlug,
        'is_active' => true,
    ]);
    $admin = createDivisionAccessUser('admin', null);
    $email = fake()->unique()->safeEmail();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Staff Divisi',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => $role,
        ])
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'email' => $email,
        'role' => $role,
        'division_id' => $division->id,
    ]);
})->with([
    'pembangunan staff' => ['staff_pembangunan', 'pembangunan'],
    'pembangunan team lead' => ['tl_pembangunan', 'pembangunan'],
    'marketing staff' => ['staff_marketing', 'marketing'],
    'marketing team lead' => ['tl_marketing', 'marketing'],
    'pemberkasan staff' => ['staff_pemberkasan', 'pemberkasan'],
    'pemberkasan team lead' => ['tl_pemberkasan', 'pemberkasan'],
]);

test('admin role changes update the account division to match', function (string $role, string $divisionSlug) {
    $pembangunan = Division::create([
        'name' => 'Pembangunan',
        'slug' => 'pembangunan',
        'is_active' => true,
    ]);
    $division = $divisionSlug === 'pembangunan'
        ? $pembangunan
        : Division::create([
            'name' => ucfirst($divisionSlug),
            'slug' => $divisionSlug,
            'is_active' => true,
        ]);
    $admin = createDivisionAccessUser('admin', null);
    $user = createDivisionAccessUser('staff_pembangunan', $pembangunan->id);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => null,
            'password_confirmation' => null,
            'role' => $role,
        ])
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => $role,
        'division_id' => $division->id,
    ]);
})->with([
    'pembangunan staff' => ['staff_pembangunan', 'pembangunan'],
    'pembangunan team lead' => ['tl_pembangunan', 'pembangunan'],
    'marketing staff' => ['staff_marketing', 'marketing'],
    'marketing team lead' => ['tl_marketing', 'marketing'],
    'pemberkasan staff' => ['staff_pemberkasan', 'pemberkasan'],
    'pemberkasan team lead' => ['tl_pemberkasan', 'pemberkasan'],
]);

test('finance denies users with a non-marketing role assigned to marketing', function () {
    $marketing = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = createDivisionAccessUser('staff_pembangunan', $marketing->id);

    $this->actingAs($user)
        ->get(route('finance.index'))
        ->assertForbidden();
});

test('reports are not visible to staff whose role conflicts with their division', function () {
    $marketing = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = createDivisionAccessUser('staff_pembangunan', $marketing->id);

    $this->actingAs($user)
        ->get(route('reports.progress.index'))
        ->assertForbidden();
});

test('staff can create progress reports for their own division', function (string $role, string $divisionSlug) {
    $division = Division::create([
        'name' => ucfirst($divisionSlug),
        'slug' => $divisionSlug,
        'is_active' => true,
    ]);
    $user = createDivisionAccessUser($role, $division->id);

    $this->actingAs($user)
        ->post(route('reports.progress.store'), [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-07',
            'title' => 'Laporan Mingguan',
            'content' => 'Perkembangan divisi.',
            'progress' => 50,
        ])
        ->assertRedirect(route('reports.progress.index'));

    $this->assertDatabaseHas('progress_reports', [
        'division_id' => $division->id,
        'created_by' => $user->id,
        'status' => 'draft',
    ]);
})->with([
    'pembangunan staff' => ['staff_pembangunan', 'pembangunan'],
    'marketing staff' => ['staff_marketing', 'marketing'],
    'pemberkasan staff' => ['staff_pemberkasan', 'pemberkasan'],
]);

test('marketing and pemberkasan staff can see the progress report submission action', function (string $role, string $divisionSlug) {
    $division = Division::create([
        'name' => ucfirst($divisionSlug),
        'slug' => $divisionSlug,
        'is_active' => true,
    ]);
    $user = createDivisionAccessUser($role, $division->id);

    $this->actingAs($user)
        ->get(route('reports.progress.index'))
        ->assertSee('Buat Laporan')
        ->assertSee('Progress Report')
        ->assertSee(route('reports.progress.index'));
})->with([
    'marketing staff' => ['staff_marketing', 'marketing'],
    'pemberkasan staff' => ['staff_pemberkasan', 'pemberkasan'],
]);

test('marketing and pemberkasan staff see a submit action for their draft reports', function (string $role, string $divisionSlug) {
    $division = Division::create([
        'name' => ucfirst($divisionSlug),
        'slug' => $divisionSlug,
        'is_active' => true,
    ]);
    $user = createDivisionAccessUser($role, $division->id);
    $report = ProgressReport::create([
        'division_id' => $division->id,
        'created_by' => $user->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-07',
        'title' => 'Laporan Mingguan',
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->get(route('reports.progress.index'))
        ->assertSee('Submit ke TL');
})->with([
    'marketing staff' => ['staff_marketing', 'marketing'],
    'pemberkasan staff' => ['staff_pemberkasan', 'pemberkasan'],
]);

test('marketing and pemberkasan staff can submit draft reports to their team lead', function (string $role, string $divisionSlug) {
    $division = Division::create([
        'name' => ucfirst($divisionSlug),
        'slug' => $divisionSlug,
        'is_active' => true,
    ]);
    $user = createDivisionAccessUser($role, $division->id);
    $report = ProgressReport::create([
        'division_id' => $division->id,
        'created_by' => $user->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-07',
        'title' => 'Laporan Mingguan',
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->post(route('reports.progress.submit', $report))
        ->assertRedirect();

    $this->assertDatabaseHas('progress_reports', [
        'id' => $report->id,
        'status' => 'submitted',
    ]);
})->with([
    'marketing staff' => ['staff_marketing', 'marketing'],
    'pemberkasan staff' => ['staff_pemberkasan', 'pemberkasan'],
]);

test('staff with a conflicting division cannot create a progress report', function () {
    $marketing = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = createDivisionAccessUser('staff_pembangunan', $marketing->id);

    $this->actingAs($user)
        ->post(route('reports.progress.store'), [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-07',
            'title' => 'Laporan Pembangunan',
            'content' => 'Perkembangan pekerjaan lapangan.',
            'progress' => 50,
        ])
        ->assertForbidden();
});

test('team leads cannot review reports outside their division', function (string $role, string $divisionSlug, string $otherDivisionSlug) {
    $division = Division::create([
        'name' => ucfirst($divisionSlug),
        'slug' => $divisionSlug,
        'is_active' => true,
    ]);
    $otherDivision = Division::create([
        'name' => ucfirst($otherDivisionSlug),
        'slug' => $otherDivisionSlug,
        'is_active' => true,
    ]);
    $teamLead = createDivisionAccessUser($role, $division->id);
    $report = ProgressReport::create([
        'division_id' => $otherDivision->id,
        'created_by' => $teamLead->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-07',
        'title' => 'Laporan Marketing',
        'status' => 'submitted',
    ]);

    $this->actingAs($teamLead)
        ->post(route('reports.progress.review', $report), ['action' => 'approved'])
        ->assertForbidden();
})->with([
    'pembangunan team lead' => ['tl_pembangunan', 'pembangunan', 'marketing'],
    'marketing team lead' => ['tl_marketing', 'marketing', 'pemberkasan'],
    'pemberkasan team lead' => ['tl_pemberkasan', 'pemberkasan', 'pembangunan'],
]);

test('team leads can see review actions for submitted reports in their division', function (string $role, string $divisionSlug) {
    $division = Division::create([
        'name' => ucfirst($divisionSlug),
        'slug' => $divisionSlug,
        'is_active' => true,
    ]);
    $teamLead = createDivisionAccessUser($role, $division->id);
    $report = ProgressReport::create([
        'division_id' => $division->id,
        'created_by' => $teamLead->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-07',
        'title' => 'Laporan Pembangunan',
        'status' => 'submitted',
    ]);

    $this->actingAs($teamLead)
        ->get(route('reports.progress.index'))
        ->assertSee('Approve');
})->with([
    'pembangunan team lead' => ['tl_pembangunan', 'pembangunan'],
    'marketing team lead' => ['tl_marketing', 'marketing'],
    'pemberkasan team lead' => ['tl_pemberkasan', 'pemberkasan'],
]);

test('team leads can approve submitted reports from their own division', function (string $role, string $divisionSlug) {
    $division = Division::create([
        'name' => ucfirst($divisionSlug),
        'slug' => $divisionSlug,
        'is_active' => true,
    ]);
    $teamLead = createDivisionAccessUser($role, $division->id);
    $report = ProgressReport::create([
        'division_id' => $division->id,
        'created_by' => $teamLead->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-07',
        'title' => 'Laporan Mingguan',
        'status' => 'submitted',
    ]);

    $this->actingAs($teamLead)
        ->post(route('reports.progress.review', $report), ['action' => 'approved'])
        ->assertRedirect();

    $this->assertDatabaseHas('progress_reports', [
        'id' => $report->id,
        'status' => 'approved',
    ]);
    $this->assertDatabaseHas('report_reviews', [
        'progress_report_id' => $report->id,
        'reviewed_by' => $teamLead->id,
        'action' => 'approved',
    ]);
})->with([
    'pembangunan team lead' => ['tl_pembangunan', 'pembangunan'],
    'marketing team lead' => ['tl_marketing', 'marketing'],
    'pemberkasan team lead' => ['tl_pemberkasan', 'pemberkasan'],
]);

test('alerts reject users whose role conflicts with their division', function () {
    $pembangunan = Division::create([
        'name' => 'Pembangunan',
        'slug' => 'pembangunan',
        'is_active' => true,
    ]);
    $user = createDivisionAccessUser('staff_marketing', $pembangunan->id);

    $this->actingAs($user)
        ->get(route('alerts.create'))
        ->assertForbidden();
});

test('marketing staff, team leads, and admins can access finance', function (string $role, ?string $divisionSlug) {
    $divisionId = $divisionSlug === null
        ? null
        : Division::create([
            'name' => ucfirst($divisionSlug),
            'slug' => $divisionSlug,
            'is_active' => true,
        ])->id;
    $user = createDivisionAccessUser($role, $divisionId);

    $this->actingAs($user)
        ->get(route('finance.index'))
        ->assertOk();
})->with([
    'marketing staff' => ['staff_marketing', 'marketing'],
    'marketing team lead' => ['tl_marketing', 'marketing'],
    'admin' => ['admin', null],
]);

function createDivisionAccessUser(string $role, ?int $divisionId): User
{
    $user = User::create([
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => 'password',
        'role' => $role,
        'division_id' => $divisionId,
    ]);
    $team = Team::factory()->personal()->create([
        'name' => $user->name."'s Team",
    ]);

    $team->members()->attach($user, [
        'role' => TeamRole::Owner->value,
    ]);
    $user->switchTeam($team);

    return $user;
}
