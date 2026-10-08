<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white">
    <flux:sidebar sticky collapsible="mobile" class="app-sidebar relative z-50 border-e border-orange-600/30 bg-orange-600 text-white">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>

                {{-- MENU ADMIN --}}
                @if(auth()->user()->role === 'admin')

                    <flux:sidebar.group :heading="__('Admin')" class="grid">

                        <flux:sidebar.item
                            icon="home"
                            :href="route('admin.dashboard')"
                            :current="request()->routeIs('admin.dashboard')"
                            wire:navigate
                        >
                            Dashboard
                        </flux:sidebar.item>

                        <flux:sidebar.item
                            icon="users"
                            :href="route('admin.users.index')"
                            :current="request()->routeIs('admin.users.*')"
                            wire:navigate
                        >
                            User Management
                        </flux:sidebar.item>

                        <flux:sidebar.item
                            icon="clipboard-document-list"
                            :href="route('admin.activity-logs.index')"
                            :current="request()->routeIs('admin.activity-logs.*')"
                            wire:navigate
                        >
                            Activity Logs
                        </flux:sidebar.item>

                    </flux:sidebar.group>

                @endif

                {{-- MENU PEMBANGUNAN --}}
                @if(auth()->user()->role === 'admin' || in_array(auth()->user()->role, ['tl_pembangunan', 'staff_pembangunan']))

                    <flux:sidebar.group :heading="__('Pembangunan')" class="grid">

                        <flux:sidebar.item
                            icon="home"
                            :href="route('pembangunan.dashboard')"
                            :current="request()->routeIs('pembangunan.dashboard')"
                            wire:navigate
                        >
                            Dashboard Pembangunan
                        </flux:sidebar.item>

                        @if(auth()->user()->role === 'admin')
                            <flux:sidebar.item
                                icon="building-office-2"
                                :href="route('admin.properties.index')"
                                :current="request()->routeIs('admin.properties.*')"
                                wire:navigate
                            >
                                Project Perumahan
                            </flux:sidebar.item>

                            <flux:sidebar.item
                                icon="building-office"
                                :href="route('admin.construction-stages.index')"
                                :current="request()->routeIs('admin.construction-stages.*')"
                                wire:navigate
                            >
                                Tahapan Pembangunan
                            </flux:sidebar.item>

                            <flux:sidebar.item
                                icon="building-office-2"
                                :href="route('admin.common-facilities.index')"
                                :current="request()->routeIs('admin.common-facilities.*')"
                                wire:navigate
                            >
                                Fasilitas Umum
                            </flux:sidebar.item>
                            @else
                                <flux:sidebar.item
                                    icon="building-office-2"
                                    :href="route('pembangunan.properties.index')"
                                    :current="request()->routeIs('pembangunan.properties.*')"
                                    wire:navigate
                                >
                                    Perumahan, Block & Kavling
                                </flux:sidebar.item>

                                <flux:sidebar.item
                                    icon="building-office"
                                    :href="route('pembangunan.construction-stages.index')"
                                    :current="request()->routeIs('pembangunan.construction-stages.*')"
                                    wire:navigate
                                >
                                    Tahapan Pembangunan
                                </flux:sidebar.item>
                            @endif

                        <flux:sidebar.item
                            icon="chart-bar"
                            :href="route('pembangunan.progress.index')"
                            :current="request()->routeIs('pembangunan.progress.*')"
                            wire:navigate
                        >
                            Progress Kavling
                        </flux:sidebar.item>

                        @if(auth()->user()->role !== 'admin')
                            <flux:sidebar.item
                                icon="building-office"
                                :href="route('pembangunan.common-facilities.index')"
                                :current="request()->routeIs('pembangunan.common-facilities.*')"
                                wire:navigate
                            >
                                Fasilitas Umum
                            </flux:sidebar.item>
                        @endif

                    </flux:sidebar.group>

                @endif


                {{-- MENU MARKETING --}}
                @if(auth()->user()->role === 'admin' || in_array(auth()->user()->role, ['tl_marketing', 'staff_marketing']))

                    <flux:sidebar.group :heading="__('Marketing & Keuangan')" class="grid">

                        <flux:sidebar.item
                            icon="home"
                            :href="route('marketing.dashboard')"
                            :current="request()->routeIs('marketing.dashboard')"
                            wire:navigate
                        >
                            Dashboard Marketing
                        </flux:sidebar.item>

                        @if(auth()->user()->role === 'admin' || auth()->user()->role === 'tl_marketing')
                            <flux:sidebar.item
                                icon="user-group"
                                :href="route('marketing.sales.index')"
                                :current="request()->routeIs('marketing.sales.*')"
                                wire:navigate
                            >
                                Data Sales
                            </flux:sidebar.item>
                        @endif

                        <flux:sidebar.item
                            icon="banknotes"
                            :href="route('finance.index')"
                            :current="request()->routeIs('finance.*')"
                            wire:navigate
                        >
                            Keuangan Marketing
                        </flux:sidebar.item>

                        <flux:sidebar.item
                            icon="map"
                            :href="route('marketing.lots.index')"
                            :current="request()->routeIs('marketing.lots.*')"
                            wire:navigate
                        >
                            Kavling
                        </flux:sidebar.item>

                        <flux:sidebar.item
                            icon="user-group"
                            :href="route('marketing.customers.index')"
                            :current="request()->routeIs('marketing.customers.*')"
                            wire:navigate
                        >
                            Customer
                        </flux:sidebar.item>

                        <flux:sidebar.item
                            icon="shopping-cart"
                            :href="route('marketing.bookings.index')"
                            :current="request()->routeIs('marketing.bookings.*')"
                            wire:navigate
                        >
                            Booking
                        </flux:sidebar.item>

                    </flux:sidebar.group>

                @endif


                {{-- MENU PEMBERKASAN --}}
                @if(auth()->user()->role === 'admin' || in_array(auth()->user()->role, ['tl_pemberkasan', 'staff_pemberkasan']))

                    <flux:sidebar.group :heading="__('Pemberkasan')" class="grid">

                        <flux:sidebar.item
                            icon="home"
                            :href="route('pemberkasan.dashboard')"
                            :current="request()->routeIs('pemberkasan.dashboard')"
                            wire:navigate
                        >
                            Dashboard Pemberkasan
                        </flux:sidebar.item>

                        @if(auth()->user()->role === 'admin' || auth()->user()->isAssignedToDivision('pemberkasan'))
                            <flux:sidebar.item
                                icon="cloud"
                                :href="route('admin.google-drive.documents.index')"
                                :current="request()->routeIs('admin.google-drive.documents.*')"
                                wire:navigate
                            >
                                Google Drive Documents
                            </flux:sidebar.item>
                        @endif

                        <flux:sidebar.item
                            icon="document-text"
                            :href="route('pemberkasan.bookings.index')"
                            :current="request()->routeIs('pemberkasan.bookings.*')"
                            wire:navigate
                        >
                            Checklist Berkas
                        </flux:sidebar.item>

                    </flux:sidebar.group>

                @endif

                {{-- MENU LAINNYA --}}
                @if(auth()->user()->isAdmin() || auth()->user()->hasValidDivisionAssignment())
                    <flux:sidebar.group :heading="__('Lainnya')" class="grid">
                        <flux:sidebar.item
                            icon="document-text"
                            :href="route('reports.progress.index')"
                            :current="request()->routeIs('reports.progress.*')"
                            wire:navigate
                        >
                            Progress Report
                        </flux:sidebar.item>

                        <flux:sidebar.item
                            icon="bell-alert"
                            :href="route('alerts.create')"
                            :current="request()->routeIs('alerts.create')"
                            wire:navigate
                        >
                            Kirim Alert
                        </flux:sidebar.item>

                        @if(auth()->user()->isAdmin())
                            <flux:sidebar.item
                                icon="bell-alert"
                                :href="route('admin.alerts.index')"
                                :current="request()->routeIs('admin.alerts.*')"
                                wire:navigate
                            >
                                Alerts
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endif

            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />

            <form method="POST" action="{{ route('logout') }}" class="hidden lg:block">
                @csrf
                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-zinc-600 hover:bg-zinc-800/5 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white">
                    <flux:icon name="arrow-right-start-on-rectangle" class="size-4" />
                    {{ __('Log out') }}
                </button>
            </form>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="relative z-40 pointer-events-auto lg:hidden">
            <flux:sidebar.collapse class="lg:hidden" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>

            <form method="POST" action="{{ route('logout') }}" class="ms-2">
                @csrf
                <button type="submit" aria-label="{{ __('Log out') }}" class="inline-flex size-10 items-center justify-center rounded-lg text-zinc-600 hover:bg-zinc-800/5 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white">
                    <flux:icon name="arrow-right-start-on-rectangle" class="size-5" />
                </button>
            </form>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @livewireScriptConfig
    </body>
</html>
