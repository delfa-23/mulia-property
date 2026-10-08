@props([
    'sidebar' => false,
])

@php($appName = config('app.name') === 'Laravel' ? 'Mulia Property' : config('app.name'))
@php($logoPath = file_exists(public_path('images/mulia-property-logo.svg'))
    ? 'images/mulia-property-logo.svg'
    : (file_exists(public_path('images/mulia-property-logo.png')) ? 'images/mulia-property-logo.png' : null))

@if($sidebar)
    <flux:sidebar.brand :name="$appName" {{ $attributes }}>
        <x-slot name="logo" class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md bg-white text-[#EB5120]">
            @if($logoPath)
                <img src="{{ asset($logoPath) }}" alt="Mulia Property" class="size-12 object-cover object-center">
            @else
                <flux:icon name="building-office-2" class="size-6" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$appName" {{ $attributes }}>
        <x-slot name="logo" class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md bg-orange-50 text-[#EB5120]">
            @if($logoPath)
                <img src="{{ asset($logoPath) }}" alt="Mulia Property" class="size-12 object-cover object-center">
            @else
                <flux:icon name="building-office-2" class="size-6" />
            @endif
        </x-slot>
    </flux:brand>
@endif
