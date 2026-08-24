@props(['title' => null, 'breadcrumbs' => []])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        {{-- Wrapper Alpine untuk collapsed state --}}
        <div
            x-data="{
                collapsed: localStorage.getItem('sidebar-collapsed') === 'true',
                isDesktop: window.innerWidth >= 1024
            }"
            x-init="
                $watch('collapsed', value => localStorage.setItem('sidebar-collapsed', value));
                
                // Tablet default collapsed
                if (window.innerWidth >= 768 && window.innerWidth < 1024 && localStorage.getItem('sidebar-collapsed') === null) {
                    collapsed = true;
                }
            "
            @resize.window="isDesktop = (window.innerWidth >= 1024)"
            class="flex min-h-screen"
        >
            {{-- Sidebar dengan dynamic width --}}
            <flux:sidebar
                sticky
                collapsible="mobile"
                class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 transition-all duration-200"
                ::class="isDesktop && collapsed ? 'lg:!w-16' : ''"
            >
                <flux:sidebar.header class="flex items-center justify-between gap-2">
                    {{-- Logo + Title (hidden saat collapsed desktop) --}}
                    <div x-show="!isDesktop || !collapsed" class="flex items-center gap-2">
                        <x-app-logo :sidebar="true" href="{{ route('admin.dashboard') }}" wire:navigate />
                    </div>

                    {{-- Logo collapsed — hover jadi expand button --}}
                    <button
                        x-show="isDesktop && collapsed"
                        @click="collapsed = false"
                        class="group relative flex h-10 w-10 items-center justify-center rounded-md transition-all duration-200
                               hover:bg-zinc-200 dark:hover:bg-zinc-800"
                        title="Expand sidebar"
                    >
                        {{-- Logo icon (visible by default) --}}
                        <span class="absolute inset-0 flex items-center justify-center transition-opacity duration-200 group-hover:opacity-0">
                            <x-app-logo-icon class="h-8 w-8" />
                        </span>
                        
                        {{-- Expand arrow (visible on hover) --}}
                        <span class="absolute inset-0 flex items-center justify-center opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                            <svg class="h-5 w-5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </button>

                    {{-- Toggle collapse button (visible saat expanded desktop) --}}
                    <button
                        x-show="isDesktop && !collapsed"
                        @click="collapsed = true"
                        class="lg:flex hidden h-8 w-8 items-center justify-center rounded-md text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800 transition-colors"
                        title="Collapse sidebar"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>

                    {{-- Mobile toggle (always visible on mobile) --}}
                    <flux:sidebar.collapse class="lg:hidden" />
                </flux:sidebar.header>

                <flux:sidebar.nav>
                    <flux:sidebar.group :heading="__('Platform')" class="grid" x-show="!isDesktop || !collapsed">
                        <flux:sidebar.item icon="chart-bar" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate.hover>
                            <span x-show="!isDesktop || !collapsed">{{ __('Dashboard') }}</span>
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    {{-- Collapsed icon-only nav items --}}
                    <template x-if="isDesktop && collapsed">
                        <div class="flex flex-col items-center gap-1 px-2">
                            <a
                                href="{{ route('admin.dashboard') }}"
                                wire:navigate.hover
                                class="flex h-10 w-10 items-center justify-center rounded-md {{ request()->routeIs('admin.dashboard') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors"
                                title="Dashboard"
                            >
                                <flux:icon icon="chart-bar" class="h-5 w-5" />
                            </a>
                        </div>
                    </template>

                    <flux:sidebar.group :heading="__('Master Data')" class="grid" x-show="!isDesktop || !collapsed">
                        <flux:sidebar.item icon="users" :href="route('admin.tim')" :current="request()->routeIs('admin.tim')" wire:navigate.hover>
                            <span x-show="!isDesktop || !collapsed">{{ __('Tim') }}</span>
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="user" :href="route('admin.personil')" :current="request()->routeIs('admin.personil')" wire:navigate.hover>
                            <span x-show="!isDesktop || !collapsed">{{ __('Personil') }}</span>
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="home-modern" :href="route('admin.ruangan')" :current="request()->routeIs('admin.ruangan')" wire:navigate.hover>
                            <span x-show="!isDesktop || !collapsed">{{ __('Ruangan') }}</span>
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    {{-- Collapsed icon-only nav items (Master Data) --}}
                    <template x-if="isDesktop && collapsed">
                        <div class="flex flex-col items-center gap-1 px-2">
                            <a href="{{ route('admin.tim') }}" wire:navigate.hover class="flex h-10 w-10 items-center justify-center rounded-md {{ request()->routeIs('admin.tim') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Tim">
                                <flux:icon icon="users" class="h-5 w-5" />
                            </a>
                            <a href="{{ route('admin.personil') }}" wire:navigate.hover class="flex h-10 w-10 items-center justify-center rounded-md {{ request()->routeIs('admin.personil') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Personil">
                                <flux:icon icon="user" class="h-5 w-5" />
                            </a>
                            <a href="{{ route('admin.ruangan') }}" wire:navigate.hover class="flex h-10 w-10 items-center justify-center rounded-md {{ request()->routeIs('admin.ruangan') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Ruangan">
                                <flux:icon icon="home-modern" class="h-5 w-5" />
                            </a>
                        </div>
                    </template>

                    <flux:sidebar.group :heading="__('Jadwal')" class="grid" x-show="!isDesktop || !collapsed">
                        <flux:sidebar.item icon="calendar-days" :href="route('admin.periode-wfo')" :current="request()->routeIs('admin.periode-wfo')" wire:navigate.hover>
                            <span x-show="!isDesktop || !collapsed">{{ __('Periode WFO') }}</span>
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calendar-days" :href="route('admin.jadwal-wfo')" :current="request()->routeIs('admin.jadwal-wfo')" wire:navigate.hover>
                            <span x-show="!isDesktop || !collapsed">{{ __('Jadwal WFO') }}</span>
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="sparkles" :href="route('admin.generate-jadwal')" :current="request()->routeIs('admin.generate-jadwal')" wire:navigate.hover>
                            <span x-show="!isDesktop || !collapsed">{{ __('Generate Jadwal') }}</span>
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="calendar-days" :href="route('admin.kalender')" :current="request()->routeIs('admin.kalender')" wire:navigate.hover>
                            <span x-show="!isDesktop || !collapsed">{{ __('Kalender') }}</span>
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    {{-- Collapsed icon-only nav items (Jadwal) --}}
                    <template x-if="isDesktop && collapsed">
                        <div class="flex flex-col items-center gap-1 px-2">
                            <a href="{{ route('admin.periode-wfo') }}" wire:navigate.hover class="flex h-10 w-10 items-center justify-center rounded-md {{ request()->routeIs('admin.periode-wfo') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Periode WFO">
                                <flux:icon icon="calendar-days" class="h-5 w-5" />
                            </a>
                            <a href="{{ route('admin.jadwal-wfo') }}" wire:navigate.hover class="flex h-10 w-10 items-center justify-center rounded-md {{ request()->routeIs('admin.jadwal-wfo') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Jadwal WFO">
                                <flux:icon icon="calendar-days" class="h-5 w-5" />
                            </a>
                            <a href="{{ route('admin.generate-jadwal') }}" wire:navigate.hover class="flex h-10 w-10 items-center justify-center rounded-md {{ request()->routeIs('admin.generate-jadwal') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Generate Jadwal">
                                <flux:icon icon="sparkles" class="h-5 w-5" />
                            </a>
                            <a href="{{ route('admin.kalender') }}" wire:navigate.hover class="flex h-10 w-10 items-center justify-center rounded-md {{ request()->routeIs('admin.kalender') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Kalender">
                                <flux:icon icon="calendar-days" class="h-5 w-5" />
                            </a>
                        </div>
                    </template>

                    <flux:sidebar.group :heading="__('Tools')" class="grid" x-show="!isDesktop || !collapsed">
                        <flux:sidebar.item icon="arrow-down-tray" :href="route('admin.export')" :current="request()->routeIs('admin.export')" wire:navigate.hover>
                            <span x-show="!isDesktop || !collapsed">{{ __('Export') }}</span>
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    {{-- Collapsed icon-only nav items (Tools) --}}
                    <template x-if="isDesktop && collapsed">
                        <div class="flex flex-col items-center gap-1 px-2">
                            <a href="{{ route('admin.export') }}" wire:navigate.hover class="flex h-10 w-10 items-center justify-center rounded-md {{ request()->routeIs('admin.export') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Export">
                                <flux:icon icon="arrow-down-tray" class="h-5 w-5" />
                            </a>
                        </div>
                    </template>
                </flux:sidebar.nav>

                <flux:spacer />

                <flux:sidebar.nav x-show="!isDesktop || !collapsed">
                    <flux:sidebar.item icon="bell" :href="route('notifikasi.index')" :current="request()->routeIs('notifikasi.index')" wire:navigate.hover>
                        {{ __('Notifikasi') }}
                    </flux:sidebar.item>
                </flux:sidebar.nav>

                {{-- Collapsed icon-only notifikasi --}}
                <flux:sidebar.nav x-show="isDesktop && collapsed">
                    <div class="flex flex-col items-center gap-1 px-2">
                        <a href="{{ route('notifikasi.index') }}" wire:navigate.hover class="flex h-10 w-10 items-center justify-center rounded-md {{ request()->routeIs('notifikasi.index') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Notifikasi">
                            <flux:icon icon="bell" class="h-5 w-5" />
                        </a>
                    </div>
                </flux:sidebar.nav>

                <div x-show="!isDesktop || !collapsed">
                    <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
                </div>

                {{-- Collapsed user avatar only --}}
                <div x-show="isDesktop && collapsed" class="hidden lg:flex justify-center p-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:dropdown position="top" align="center">
                        <button type="button" class="focus:outline-none focus:ring-2 focus:ring-brand rounded-full">
                            <flux:avatar
                                :name="auth()->user()->name"
                                :initials="auth()->user()->initials()"
                                size="sm"
                            />
                        </button>
                        <flux:menu>
                            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate.hover>
                                {{ __('Settings') }}
                            </flux:menu.item>
                            <flux:menu.separator />
                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                                    {{ __('Log out') }}
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </flux:sidebar>

            {{-- Main content dengan dynamic padding --}}
            <div class="flex-1 flex flex-col min-w-0 transition-all duration-200" ::class="isDesktop && collapsed ? 'lg:ml-0' : ''">
                <!-- Mobile User Menu -->
                <flux:header class="lg:hidden">
                    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
                    <flux:spacer />
                    <flux:dropdown position="top" align="end">
                        <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                        <flux:menu>
                            <flux:menu.radio.group>
                                <div class="p-0 text-sm font-normal">
                                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                        <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                                        <div class="grid flex-1 text-start text-sm leading-tight">
                                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                        </div>
                                    </div>
                                </div>
                            </flux:menu.radio.group>
                            <flux:menu.separator />
                            <flux:menu.radio.group>
                                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate.hover>
                                    {{ __('Settings') }}
                                </flux:menu.item>
                            </flux:menu.radio.group>
                            <flux:menu.separator />
                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                                    {{ __('Log out') }}
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                </flux:header>

                <flux:main>
                    @if (count($breadcrumbs) > 0 || $title)
                        <div class="mb-4">
                            <flux:breadcrumbs class="text-sm">
                                <flux:breadcrumbs.item :href="route('admin.dashboard')" wire:navigate.hover>Dashboard</flux:breadcrumbs.item>
                                @foreach ($breadcrumbs as $crumb)
                                    @if (isset($crumb['href']))
                                        <flux:breadcrumbs.item :href="$crumb['href']" wire:navigate.hover>{{ $crumb['label'] }}</flux:breadcrumbs.item>
                                    @else
                                        <flux:breadcrumbs.item>{{ $crumb['label'] }}</flux:breadcrumbs.item>
                                    @endif
                                @endforeach
                                @if ($title && (count($breadcrumbs) === 0 || end($breadcrumbs)['label'] !== $title))
                                    <flux:breadcrumbs.item>{{ $title }}</flux:breadcrumbs.item>
                                @endif
                            </flux:breadcrumbs>
                        </div>
                    @endif

                    {{ $slot }}
                </flux:main>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist>

        @fluxScripts
    </body>
</html>
