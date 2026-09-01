@props(['title' => null, 'breadcrumbs' => []])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        {{-- Sidebar dengan Flux standard layout --}}
        <flux:sidebar
            sticky
            collapsible
            class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
        >
            {{-- Sticky Header --}}
            <flux:sidebar.header>
                <flux:sidebar.brand
                    href="{{ route('admin.dashboard') }}"
                    logo="/favicon.svg"
                    name="Giliran"
                />
                <flux:sidebar.collapse />
            </flux:sidebar.header>

            {{-- Scrollable Navigation Area --}}
            <flux:sidebar.nav>
                {{-- Platform --}}
                <flux:sidebar.item icon="chart-bar" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate.hover>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                {{-- Master Data --}}
                <flux:sidebar.item icon="users" :href="route('admin.tim')" :current="request()->routeIs('admin.tim')" wire:navigate.hover>
                    {{ __('Tim') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="user" :href="route('admin.personil')" :current="request()->routeIs('admin.personil')" wire:navigate.hover>
                    {{ __('Personil') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="home-modern" :href="route('admin.ruangan')" :current="request()->routeIs('admin.ruangan')" wire:navigate.hover>
                    {{ __('Ruangan') }}
                </flux:sidebar.item>

                {{-- Jadwal --}}
                <flux:sidebar.item icon="calendar-days" :href="route('admin.periode-wfo')" :current="request()->routeIs('admin.periode-wfo')" wire:navigate.hover>
                    {{ __('Periode WFO') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="calendar-days" :href="route('admin.jadwal-wfo')" :current="request()->routeIs('admin.jadwal-wfo')" wire:navigate.hover>
                    {{ __('Jadwal WFO') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="building-office-2" :href="route('admin.alokasi-ruangan')" :current="request()->routeIs('admin.alokasi-ruangan')" wire:navigate.hover>
                    {{ __('Alokasi Ruangan') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="sparkles" :href="route('admin.generate-jadwal')" :current="request()->routeIs('admin.generate-jadwal')" wire:navigate.hover>
                    {{ __('Generate Jadwal') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{-- Navbar Atas: Breadcrumb (kiri) + Notifikasi & Profile (kanan) --}}
        <flux:header class="bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border-b border-zinc-200/60 dark:border-zinc-700/60 sticky top-0 z-20">
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
                            str_starts_with($routeName, 'admin.alokasi-ruangan') || 
                            str_starts_with($routeName, 'admin.generate-jadwal')) {
                        $breadcrumbParts[] = ['label' => 'Jadwal'];
                        if (str_starts_with($routeName, 'admin.periode-wfo')) {
                            $breadcrumbParts[] = ['label' => 'Periode WFO'];
                        } elseif (str_starts_with($routeName, 'admin.jadwal-wfo')) {
                            $breadcrumbParts[] = ['label' => 'Jadwal WFO'];
                        } elseif (str_starts_with($routeName, 'admin.alokasi-ruangan')) {
                            $breadcrumbParts[] = ['label' => 'Alokasi Ruangan'];
                        } elseif (str_starts_with($routeName, 'admin.generate-jadwal')) {
                            $breadcrumbParts[] = ['label' => 'Generate Jadwal'];
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
                {{-- Notifikasi Dropdown --}}
                <livewire:notifikasi-dropdown />

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

        <flux:main class="!pb-0">
            {{ $slot }}
        </flux:main>

        {{-- DreamsPOS-style Footer via official flux:footer --}}
        <flux:footer class="border-t border-zinc-200/60 dark:border-zinc-800 bg-white/50 dark:bg-zinc-900/50 !py-3.5 !px-6 text-xs text-zinc-500 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div class="flex items-center gap-1.5">
                <span>2026 © <strong class="font-semibold text-zinc-700 dark:text-zinc-300">Giliran</strong> — PT Inovindo Digital Media. All Rights Reserved</span>
            </div>
            <div class="flex items-center gap-1">
                <span>Designed & Developed by</span>
                <a href="https://obikusakabe.github.io/MyPortfolio/" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#3B71CA] dark:text-blue-400 hover:underline">Roby Rachmat Firdaus</a>
            </div>
        </flux:footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
