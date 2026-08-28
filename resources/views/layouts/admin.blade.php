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
            {{-- Sidebar dengan Flux demo style --}}
            <flux:sidebar
                sticky
                collapsible="mobile"
                class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 transition-all duration-200 flex flex-col [:where(&)]:w-64 data-flux-sidebar-collapsed-desktop:w-14"
                ::class="isDesktop && collapsed ? 'lg:!w-14' : ''"
            >
                {{-- Sticky Header --}}
                <flux:sidebar.header class="flex items-center justify-between gap-2 shrink-0 min-h-10">
                    {{-- Logo + Title (hidden saat collapsed desktop) --}}
                    <div x-show="!isDesktop || !collapsed" class="flex items-center gap-2 min-w-0 flex-1">
                        <x-app-logo :sidebar="true" href="{{ route('admin.dashboard') }}" wire:navigate />
                    </div>

                    {{-- Logo collapsed — hover jadi expand button --}}
                    <button
                        x-show="isDesktop && collapsed"
                        @click="collapsed = false"
                        class="group relative flex h-10 w-10 shrink-0 items-center justify-center rounded-md transition-all duration-200
                               hover:bg-zinc-200 dark:hover:bg-zinc-800 cursor-e-resize rtl:cursor-w-resize"
                        title="Expand sidebar"
                    >
                        <x-app-logo-icon class="h-6 w-6" />
                    </button>

                    {{-- Toggle collapse button - Flux demo style (panel icon) --}}
                    <button
                        x-show="isDesktop && !collapsed"
                        @click="collapsed = true"
                        class="lg:flex hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg text-zinc-500 hover:text-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-800 dark:text-zinc-400 dark:hover:text-white transition-colors rtl:rotate-180 -mr-2"
                        title="Toggle sidebar"
                    >
                        <svg class="text-zinc-500 dark:text-zinc-400" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7.5 3.75V16.25M3.4375 16.25H16.5625C17.08 16.25 17.5 15.83 17.5 15.3125V4.6875C17.5 4.17 17.08 3.75 16.5625 3.75H3.4375C2.92 3.75 2.5 4.17 2.5 4.6875V15.3125C2.5 15.83 2.92 16.25 3.4375 16.25Z" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    {{-- Mobile toggle (always visible on mobile) --}}
                    <flux:sidebar.collapse class="lg:hidden" />
                </flux:sidebar.header>

                {{-- Scrollable Navigation Area --}}
                <div class="flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-zinc-300 dark:scrollbar-thumb-zinc-700 scrollbar-track-transparent">
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
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.dashboard') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors"
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
                            <a href="{{ route('admin.tim') }}" wire:navigate.hover class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.tim') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Tim">
                                <flux:icon icon="users" class="h-5 w-5" />
                            </a>
                            <a href="{{ route('admin.personil') }}" wire:navigate.hover class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.personil') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Personil">
                                <flux:icon icon="user" class="h-5 w-5" />
                            </a>
                            <a href="{{ route('admin.ruangan') }}" wire:navigate.hover class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.ruangan') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Ruangan">
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
                            <a href="{{ route('admin.periode-wfo') }}" wire:navigate.hover class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.periode-wfo') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Periode WFO">
                                <flux:icon icon="calendar-days" class="h-5 w-5" />
                            </a>
                            <a href="{{ route('admin.jadwal-wfo') }}" wire:navigate.hover class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.jadwal-wfo') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Jadwal WFO">
                                <flux:icon icon="calendar-days" class="h-5 w-5" />
                            </a>
                            <a href="{{ route('admin.generate-jadwal') }}" wire:navigate.hover class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.generate-jadwal') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Generate Jadwal">
                                <flux:icon icon="sparkles" class="h-5 w-5" />
                            </a>
                            <a href="{{ route('admin.kalender') }}" wire:navigate.hover class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.kalender') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Kalender">
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
                            <a href="{{ route('admin.export') }}" wire:navigate.hover class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.export') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors" title="Export">
                                <flux:icon icon="arrow-down-tray" class="h-5 w-5" />
                            </a>
                        </div>
                    </template>
                </flux:sidebar.nav>
                </div>
            </flux:sidebar>

            {{-- Main content dengan dynamic padding --}}
            <div class="flex-1 flex flex-col min-w-0 transition-all duration-200" ::class="isDesktop && collapsed ? 'lg:ml-0' : ''">
                {{-- Navbar Atas: Breadcrumb (kiri) + Notifikasi & Profile (kanan) --}}
                <flux:header class="bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700 sticky top-0 z-10">
                    {{-- Mobile hamburger --}}
                    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
                    
                    {{-- Breadcrumb (desktop only, hidden on mobile) --}}
                    <div class="hidden lg:block">
                        @php
                            // Smart breadcrumb detection based on route
                            $routeName = request()->route()->getName();
                            $breadcrumbParts = [];
                            
                            // Determine category and page
                            if (str_starts_with($routeName, 'admin.dashboard')) {
                                $breadcrumbParts = [['label' => 'Dashboard']];
                            } 
                            elseif (str_starts_with($routeName, 'admin.tim') || 
                                    str_starts_with($routeName, 'admin.personil') || 
                                    str_starts_with($routeName, 'admin.ruangan')) {
                                $breadcrumbParts[] = ['label' => 'Master Data'];
                                if (str_starts_with($routeName, 'admin.tim')) {
                                    $breadcrumbParts[] = ['label' => 'Tim'];
                                } elseif (str_starts_with($routeName, 'admin.personil')) {
                                    $breadcrumbParts[] = ['label' => 'Personil'];
                                } elseif (str_starts_with($routeName, 'admin.ruangan')) {
                                    $breadcrumbParts[] = ['label' => 'Ruangan'];
                                }
                            }
                            elseif (str_starts_with($routeName, 'admin.periode-wfo') || 
                                    str_starts_with($routeName, 'admin.jadwal-wfo') || 
                                    str_starts_with($routeName, 'admin.generate-jadwal') ||
                                    str_starts_with($routeName, 'admin.kalender')) {
                                $breadcrumbParts[] = ['label' => 'Jadwal'];
                                if (str_starts_with($routeName, 'admin.periode-wfo')) {
                                    $breadcrumbParts[] = ['label' => 'Periode WFO'];
                                } elseif (str_starts_with($routeName, 'admin.jadwal-wfo')) {
                                    $breadcrumbParts[] = ['label' => 'Jadwal WFO'];
                                } elseif (str_starts_with($routeName, 'admin.generate-jadwal')) {
                                    $breadcrumbParts[] = ['label' => 'Generate Jadwal'];
                                } elseif (str_starts_with($routeName, 'admin.kalender')) {
                                    $breadcrumbParts[] = ['label' => 'Kalender'];
                                }
                            }
                            elseif (str_starts_with($routeName, 'admin.export')) {
                                $breadcrumbParts[] = ['label' => 'Tools'];
                                $breadcrumbParts[] = ['label' => 'Export'];
                            }
                            elseif (str_starts_with($routeName, 'notifikasi')) {
                                $breadcrumbParts[] = ['label' => 'Notifikasi'];
                            }
                            elseif (str_starts_with($routeName, 'profile') || 
                                    str_starts_with($routeName, 'security') || 
                                    str_starts_with($routeName, 'appearance')) {
                                // Settings pages already have breadcrumbs prop
                                $breadcrumbParts = [];
                            }
                            else {
                                // Fallback: use title if available
                                if ($title) {
                                    $breadcrumbParts[] = ['label' => $title];
                                }
                            }
                            
                            // Merge with manual breadcrumbs if provided
                            if (!empty($breadcrumbs)) {
                                $breadcrumbParts = $breadcrumbs;
                            }
                        @endphp
                        
                        @if (count($breadcrumbParts) > 0)
                            <flux:breadcrumbs class="text-sm">
                                @foreach ($breadcrumbParts as $index => $crumb)
                                    @if (isset($crumb['href']))
                                        <flux:breadcrumbs.item :href="$crumb['href']" wire:navigate.hover>{{ $crumb['label'] }}</flux:breadcrumbs.item>
                                    @else
                                        <flux:breadcrumbs.item>{{ $crumb['label'] }}</flux:breadcrumbs.item>
                                    @endif
                                @endforeach
                            </flux:breadcrumbs>
                        @endif
                    </div>

                    <flux:spacer />

                    {{-- Notifikasi + Profile (kanan) --}}
                    <div class="flex items-center gap-2">
                        {{-- Notifikasi Bell Icon --}}
                        <a 
                            href="{{ route('notifikasi.index') }}" 
                            wire:navigate.hover
                            class="relative flex h-10 w-10 items-center justify-center rounded-md {{ request()->routeIs('notifikasi.index') ? 'bg-brand text-white' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800' }} transition-colors"
                            title="Notifikasi"
                        >
                            <flux:icon icon="bell" class="h-5 w-5" />
                            {{-- Badge notifikasi (optional, bisa diaktifkan nanti) --}}
                            {{-- <span class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500"></span> --}}
                        </a>

                        {{-- Profile Dropdown --}}
                        <flux:dropdown position="bottom" align="end">
                            <flux:profile
                                :initials="auth()->user()->initials()"
                                icon-trailing="chevron-down"
                            />

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
                    </div>
                </flux:header>

                <flux:main>
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
