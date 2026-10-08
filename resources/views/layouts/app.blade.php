<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="app-main">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
