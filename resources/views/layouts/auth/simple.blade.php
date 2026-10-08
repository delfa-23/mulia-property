<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="auth-shell min-h-screen bg-[#F7F9FC] text-[#10233D] antialiased">
        @php
            $brandLogoPath = file_exists(public_path('images/mulia-property-logo.svg'))
                ? 'images/mulia-property-logo.svg'
                : (file_exists(public_path('images/mulia-property-logo.png'))
                    ? 'images/mulia-property-logo.png'
                    : null);
        @endphp

        <div class="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
            <section class="relative isolate flex min-h-[230px] items-end overflow-hidden bg-[#10233D] sm:min-h-[280px] lg:min-h-screen">
                <img src="{{ asset('images/mulia-residence.jpg') }}" alt="Kawasan perumahan Mulia Residence" fetchpriority="high" class="absolute inset-0 size-full object-cover object-center">
                <div class="absolute inset-0 bg-[#10233D]/55"></div>
                <div class="relative z-10 w-full p-6 text-white sm:p-10 lg:p-14">
                    <a href="{{ route('home') }}" class="mb-8 inline-flex items-center gap-3" style="margin-left: -0.5rem;" wire:navigate>
                        @if($brandLogoPath)
                            <img src="{{ asset($brandLogoPath) }}" alt="Mulia Property" class="h-16 w-56 object-cover object-center">
                        @else
                            <span class="flex size-10 items-center justify-center rounded-lg bg-[#EB5120] text-white">
                                <flux:icon name="building-office-2" class="size-5" />
                            </span>
                            <span class="text-sm font-black uppercase tracking-[0.14em]">Mulia Property</span>
                        @endif
                    </a>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-orange-200">Mulia Residence</p>
                    <h1 class="mt-2 max-w-xl text-3xl font-black leading-tight sm:text-4xl lg:text-5xl">Ruang untuk tumbuh, dikelola dengan terarah.</h1>
                    <p class="mt-3 max-w-lg text-sm leading-relaxed text-white/85 sm:text-base">Satu akses untuk memantau proyek, penjualan, pembangunan, dan pemberkasan.</p>
                </div>
            </section>

            <main class="flex min-h-[610px] items-center justify-center px-5 py-10 sm:px-8 lg:min-h-screen lg:px-12">
                <div class="w-full max-w-md">
                    <div class="mb-8 lg:hidden" style="margin-left: -0.5rem;">
                        @if($brandLogoPath)
                            <img src="{{ asset($brandLogoPath) }}" alt="Mulia Property" class="h-14 w-52 object-cover object-center">
                        @else
                            <div class="flex items-center gap-3 text-[#10233D]">
                                <span class="flex size-9 items-center justify-center rounded-lg bg-[#EB5120] text-white"><flux:icon name="building-office-2" class="size-5" /></span>
                                <span class="text-sm font-black uppercase tracking-[0.14em]">Mulia Property</span>
                            </div>
                        @endif
                    </div>

                    {{ $slot }}

                    <p class="mt-8 text-center text-xs text-slate-400">&copy; {{ now()->year }} Mulia Property</p>
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @livewireScriptConfig
    </body>
</html>
