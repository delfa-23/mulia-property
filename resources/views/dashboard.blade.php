<x-layouts::app :title="__('Dashboard')">
    <livewire:pages::teams.pending-invitations-modal />

    @php
        $dashboardContent = match (auth()->user()->role) {
            'admin' => [
                'title' => 'Admin Dashboard',
                'description' => 'Overview of all property operations and team activity.',
                'items' => ['Manage users and roles', 'Review all divisions', 'Monitor property performance'],
            ],
            'tl_pembangunan' => [
                'title' => 'Pembangunan Team Lead Dashboard',
                'description' => 'Track construction progress and team assignments.',
                'items' => ['Review construction progress', 'Approve team updates', 'Monitor project stages'],
            ],
            'staff_pembangunan' => [
                'title' => 'Pembangunan Staff Dashboard',
                'description' => 'Record and follow up on construction work.',
                'items' => ['Update construction progress', 'View assigned work', 'Submit progress reports'],
            ],
            'tl_marketing' => [
                'title' => 'Marketing Team Lead Dashboard',
                'description' => 'Manage sales activity and marketing performance.',
                'items' => ['Review booking activity', 'Monitor sales performance', 'Manage marketing tasks'],
            ],
            'staff_marketing' => [
                'title' => 'Marketing Staff Dashboard',
                'description' => 'Work with customer leads and property bookings.',
                'items' => ['Manage customer leads', 'Create property bookings', 'Follow up with customers'],
            ],
            'tl_pemberkasan' => [
                'title' => 'Pemberkasan Team Lead Dashboard',
                'description' => 'Coordinate document processing and approvals.',
                'items' => ['Review document processes', 'Approve completed documents', 'Monitor pending work'],
            ],
            'staff_pemberkasan' => [
                'title' => 'Pemberkasan Staff Dashboard',
                'description' => 'Process customer documents and administrative records.',
                'items' => ['Process assigned documents', 'Update document status', 'Submit completed work'],
            ],
            default => [
                'title' => 'Dashboard',
                'description' => 'Welcome to Mulia Property.',
                'items' => ['View your work', 'Review recent activity', 'Update your profile'],
            ],
        };
    @endphp

    <div class="mb-6">
        <flux:heading size="xl">{{ $dashboardContent['title'] }}</flux:heading>
        <flux:text class="mt-2">{{ $dashboardContent['description'] }}</flux:text>
    </div>

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            @foreach ($dashboardContent['items'] as $item)
                <div class="relative overflow-hidden rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
                    <flux:text>{{ $item }}</flux:text>
                </div>
            @endforeach
        </div>
        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div>
    </div>
</x-layouts::app>
