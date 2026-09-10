@props(['title' => null, 'breadcrumbs' => []])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body 
        class="min-h-screen bg-white dark:bg-zinc-800"
        x-data="{
            sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
            init() {
                // Apply initial state immediately (before Flux loads)
                this.applySidebarState();
                
                // Listen to Flux sidebar toggle events
                this.$watch('sidebarCollapsed', value => {
                    localStorage.setItem('sidebarCollapsed', value);
                    this.applySidebarState();
                });
                
                // Intercept Flux sidebar collapse toggle
                document.addEventListener('click', (e) => {
                    const collapseBtn = e.target.closest('[data-flux-sidebar-collapse]');
                    if (collapseBtn) {
                        // Wait for Flux to apply its state, then sync
                        setTimeout(() => {
                            const sidebar = document.querySelector('[data-flux-sidebar]');
                            if (sidebar) {
                                const isCollapsed = sidebar.hasAttribute('data-flux-sidebar-collapsed-desktop');
                                this.sidebarCollapsed = isCollapsed;
                            }
                        }, 50);
                    }
                });
                
                // On page load/navigate, reapply state
                document.addEventListener('livewire:navigated', () => {
                    this.applySidebarState();
                });
            },
            applySidebarState() {
                const sidebar = document.querySelector('[data-flux-sidebar]');
                if (!sidebar) return;
                
                if (this.sidebarCollapsed) {
                    // Collapse sidebar
                    sidebar.setAttribute('data-flux-sidebar-collapsed-desktop', '');
                } else {
                    // Expand sidebar
                    sidebar.removeAttribute('data-flux-sidebar-collapsed-desktop');
                }
            }
        }"
    >
        {{-- Sidebar dengan Flux standard layout --}}
        <flux:sidebar
            sticky
            collapsible
            class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
        >
            {{-- Header Sidebar / Brand --}}
            <flux:sidebar.header>
                <x-app-logo 
                    sidebar
                    href="{{ auth()->user()?->hasRole('admin') ? route('admin.dashboard') : route('tim.beranda') }}"
                />
                <flux:sidebar.collapse />
            </flux:sidebar.header>

            {{-- Navigasi Sidebar --}}
            <flux:sidebar.nav>
                @if (auth()->user()?->hasRole('admin'))
                    {{-- Navigasi Admin --}}
                    <flux:sidebar.item icon="chart-bar" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate.hover>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="users" :href="route('admin.tim')" :current="request()->routeIs('admin.tim')" wire:navigate.hover>
                        {{ __('Tim') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user" :href="route('admin.personil')" :current="request()->routeIs('admin.personil')" wire:navigate.hover>
                        {{ __('Personil') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="home-modern" :href="route('admin.ruangan')" :current="request()->routeIs('admin.ruangan')" wire:navigate.hover>
                        {{ __('Ruangan') }}
                    </flux:sidebar.item>

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

                    @php
                        $unviewedCount = \App\Models\NotulenBriefing::unviewed()->count();
                    @endphp
                    <flux:sidebar.item 
                        icon="clipboard-document-list" 
                        :href="route('admin.notulen-briefing')" 
                        :current="request()->routeIs('admin.notulen-briefing')" 
                        wire:navigate.hover
                        tooltip="Notulen Briefing"
                    >
                        <div class="flex items-center justify-between w-full">
                            <span>Notulen Briefing</span>
                            @if ($unviewedCount > 0)
                                <flux:badge size="sm" class="bg-red-500 text-white font-semibold">
                                    {{ $unviewedCount }}
                                </flux:badge>
                            @endif
                        </div>
                    </flux:sidebar.item>
                @else
                    {{-- Navigasi Tim Portal --}}
                    <flux:sidebar.item icon="home" :href="route('tim.beranda')" :current="request()->routeIs('tim.beranda')" wire:navigate.hover>
                        {{ __('Beranda') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="calendar-days" :href="route('tim.jadwal')" :current="request()->routeIs('tim.jadwal')" wire:navigate.hover>
                        {{ __('Jadwal Tim') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="home-modern" :href="route('tim.ruangan')" :current="request()->routeIs('tim.ruangan')" wire:navigate.hover>
                        {{ __('Ruangan Hari Ini') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('tim.notulen.history')" :current="request()->routeIs('tim.notulen.*')" wire:navigate.hover>
                        {{ __('Notulen Briefing') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-group" :href="route('tim.profil')" :current="request()->routeIs('tim.profil') || request()->routeIs('tim.akun')" wire:navigate.hover>
                        {{ __('Profil & Anggota') }}
                    </flux:sidebar.item>
                @endif
            </flux:sidebar.nav>

            {{-- Dev Mode Toggle (Sidebar Footer) - Available for all authenticated users --}}
            <div 
                class="mt-auto border-t border-zinc-200 dark:border-zinc-700 p-4"
                x-data="{
                    devMode: localStorage.getItem('devMode') === 'true',
                    devTime: localStorage.getItem('devTime') || '',
                    init() {
                        // Apply dev mode on page load
                        if (this.devMode && this.devTime) {
                            this.applyDevMode();
                        }
                        
                        // Watch for changes
                        this.$watch('devMode', value => {
                            localStorage.setItem('devMode', value);
                            if (!value) {
                                // Reset to real time when disabled
                                localStorage.removeItem('devTime');
                                this.devTime = '';
                                this.resetDevMode();
                            }
                        });
                        
                        this.$watch('devTime', value => {
                            if (this.devMode && value) {
                                localStorage.setItem('devTime', value);
                                this.applyDevMode();
                            }
                        });
                    },
                    applyDevMode() {
                        // Send dev mode time to server via Livewire event
                        if (window.Livewire) {
                            window.Livewire.dispatch('dev-mode-time-changed', { time: this.devTime });
                        }
                        // Also store in session via AJAX
                        fetch('/api/dev-mode/set-time', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                            },
                            body: JSON.stringify({ time: this.devTime })
                        });
                    },
                    resetDevMode() {
                        if (window.Livewire) {
                            window.Livewire.dispatch('dev-mode-time-changed', { time: null });
                        }
                        fetch('/api/dev-mode/reset-time', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                            }
                        });
                    }
                }"
            >
                <div class="flex items-center justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2">
                        <flux:icon icon="beaker" class="size-4 text-amber-500" />
                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Dev Mode</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input 
                            type="checkbox" 
                            x-model="devMode"
                            class="sr-only peer"
                        >
                        <div class="w-9 h-5 bg-zinc-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-amber-300 dark:peer-focus:ring-amber-800 rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-zinc-600 peer-checked:bg-amber-500"></div>
                    </label>
                </div>
                
                {{-- Time Picker --}}
                <div x-show="devMode" x-transition x-cloak class="mt-3">
                    <label class="block text-[11px] font-medium text-zinc-600 dark:text-zinc-400 mb-1">
                        Simulasi Waktu
                    </label>
                    <input 
                        type="datetime-local" 
                        x-model="devTime"
                        class="w-full px-2.5 py-1.5 text-xs border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                        placeholder="Pilih tanggal & waktu"
                    >
                    <p class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-1.5">
                        Sistem akan menggunakan waktu ini untuk simulasi fitur
                    </p>
                </div>
            </div>
        </flux:sidebar>

        {{-- Navbar Atas --}}
        <flux:header class="bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border-b border-zinc-200/60 dark:border-zinc-700/60 sticky top-0 z-20">
            @if (auth()->user()?->hasRole('admin'))
                <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            @else
                <div class="flex items-center gap-2.5 lg:hidden">
                    <img src="/favicon.svg" alt="Giliran" class="h-7 w-7">
                    <span class="font-bold text-zinc-900 dark:text-zinc-100">Giliran</span>
                </div>
            @endif
            
            <div class="hidden lg:block">
                @php
                    $routeName = request()->route()->getName();
                    $breadcrumbParts = [];
                    
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
                    elseif (str_starts_with($routeName, 'admin.')) {
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
                    elseif (str_starts_with($routeName, 'tim.')) {
                        // Use 'Beranda' as root for tim pages, not 'Portal Tim'
                        if (str_starts_with($routeName, 'tim.beranda')) {
                            $breadcrumbParts[] = ['label' => 'Beranda'];
                        } elseif (str_starts_with($routeName, 'tim.jadwal')) {
                            $breadcrumbParts[] = ['label' => 'Beranda', 'href' => route('tim.beranda')];
                            $breadcrumbParts[] = ['label' => 'Jadwal Tim'];
                        } elseif (str_starts_with($routeName, 'tim.ruangan')) {
                            $breadcrumbParts[] = ['label' => 'Beranda', 'href' => route('tim.beranda')];
                            $breadcrumbParts[] = ['label' => 'Ruangan Hari Ini'];
                        } elseif (str_starts_with($routeName, 'tim.notulen')) {
                            $breadcrumbParts[] = ['label' => 'Beranda', 'href' => route('tim.beranda')];
                            $breadcrumbParts[] = ['label' => 'Notulen Briefing'];
                            if (str_starts_with($routeName, 'tim.notulen.create')) {
                                $breadcrumbParts[] = ['label' => 'Isi Notulen'];
                            } elseif (str_starts_with($routeName, 'tim.notulen.history')) {
                                $breadcrumbParts[] = ['label' => 'Riwayat'];
                            }
                        } elseif (str_starts_with($routeName, 'tim.profil') || str_starts_with($routeName, 'tim.akun')) {
                            $breadcrumbParts[] = ['label' => 'Beranda', 'href' => route('tim.beranda')];
                            $breadcrumbParts[] = ['label' => 'Profil & Anggota'];
                        }
                    }
                    elseif ($title) {
                        $breadcrumbParts[] = ['label' => $title];
                    }
                    
                    if (!empty($breadcrumbs)) {
                        $breadcrumbParts = $breadcrumbs;
                    }
                @endphp
                
                @if (count($breadcrumbParts) > 0)
                    <flux:breadcrumbs class="text-sm">
                        @foreach ($breadcrumbParts as $crumb)
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

            {{-- Dev Mode Indicator (when active) --}}
            <div 
                x-data="{ 
                    devMode: localStorage.getItem('devMode') === 'true',
                    devTime: localStorage.getItem('devTime') || ''
                }"
                x-show="devMode && devTime"
                x-cloak
                class="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-amber-100 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700"
            >
                <flux:icon icon="beaker" class="size-4 text-amber-600 dark:text-amber-400" />
                <div class="text-xs">
                    <span class="font-semibold text-amber-700 dark:text-amber-300">Dev Mode</span>
                    <span class="text-amber-600 dark:text-amber-400 ml-1" x-text="devTime ? '(' + new Date(devTime).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }) + ')' : ''"></span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <livewire:notifikasi-dropdown />

                <flux:dropdown position="bottom" align="end">
                    <flux:profile
                        :src="auth()->user()->tim?->foto_bersama ? \Illuminate\Support\Facades\Storage::url(auth()->user()->tim->foto_bersama) : null"
                        :initials="auth()->user()->initials()"
                        icon-trailing="chevron-down"
                    />

                    <flux:menu>
                        <flux:menu.radio.group>
                            <div class="p-0 text-sm font-normal">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                    <flux:avatar 
                                        :src="auth()->user()->isTim() && auth()->user()->tim?->foto_bersama ? \Illuminate\Support\Facades\Storage::url(auth()->user()->tim->foto_bersama) : null"
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

        <flux:main class="{{ !auth()->user()?->hasRole('admin') ? 'pb-24 lg:!pb-0' : '!pb-0' }}">
            {{ $slot }}
        </flux:main>

        {{-- DreamsPOS-style Footer --}}
        <flux:footer class="{{ !auth()->user()?->hasRole('admin') ? 'mb-16 lg:mb-0' : '' }} border-t border-zinc-200/60 dark:border-zinc-800 bg-white/50 dark:bg-zinc-900/50 !py-3.5 !px-6 text-xs text-zinc-500 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div class="flex items-center gap-1.5">
                <span>2026 © <strong class="font-semibold text-zinc-700 dark:text-zinc-300">Giliran</strong> — PT Inovindo Digital Media. All Rights Reserved</span>
            </div>
            <div class="flex items-center gap-1">
                <span>Designed & Developed by</span>
                <a href="https://obikusakabe.github.io/MyPortfolio/" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#3B71CA] dark:text-blue-400 hover:underline">Roby Rachmat Firdaus</a>
            </div>
        </flux:footer>

        @if (!auth()->user()?->hasRole('admin'))
            {{-- Mobile & Tablet Bottom Navigation Bar (< 1024px) --}}
            <nav class="fixed bottom-0 left-0 right-0 bg-white dark:bg-zinc-800 border-t border-zinc-200 dark:border-zinc-700 shadow-[0_-2px_10px_rgba(0,0,0,0.05)] safe-area-bottom z-30 lg:hidden">
                <div class="max-w-lg mx-auto">
                    <div class="flex justify-around items-center h-16">
                        {{-- Tab 1: Beranda --}}
                        <a 
                            href="{{ route('tim.beranda') }}"
                            wire:navigate
                            class="flex flex-col items-center justify-center flex-1 h-full transition-colors {{ request()->routeIs('tim.beranda') ? 'text-[#3B71CA] dark:text-blue-400' : 'text-gray-400 dark:text-gray-500' }}"
                        >
                            @if(request()->routeIs('tim.beranda'))
                                <flux:icon.home variant="solid" class="w-6 h-6" />
                            @else
                                <flux:icon.home variant="outline" class="w-6 h-6" />
                            @endif
                            <span class="text-xs mt-1 font-medium">Beranda</span>
                        </a>

                        {{-- Tab 2: Jadwal --}}
                        <a 
                            href="{{ route('tim.jadwal') }}"
                            wire:navigate
                            class="flex flex-col items-center justify-center flex-1 h-full transition-colors {{ request()->routeIs('tim.jadwal') ? 'text-[#3B71CA] dark:text-blue-400' : 'text-gray-400 dark:text-gray-500' }}"
                        >
                            @if(request()->routeIs('tim.jadwal'))
                                <flux:icon.calendar-days variant="solid" class="w-6 h-6" />
                            @else
                                <flux:icon.calendar-days variant="outline" class="w-6 h-6" />
                            @endif
                            <span class="text-xs mt-1 font-medium">Jadwal</span>
                        </a>

                        {{-- Tab 3: Ruangan --}}
                        <a 
                            href="{{ route('tim.ruangan') }}"
                            wire:navigate
                            class="flex flex-col items-center justify-center flex-1 h-full transition-colors {{ request()->routeIs('tim.ruangan') ? 'text-[#3B71CA] dark:text-blue-400' : 'text-gray-400 dark:text-gray-500' }}"
                        >
                            @if(request()->routeIs('tim.ruangan'))
                                <flux:icon.building-office-2 variant="solid" class="w-6 h-6" />
                            @else
                                <flux:icon.building-office-2 variant="outline" class="w-6 h-6" />
                            @endif
                            <span class="text-xs mt-1 font-medium">Ruangan</span>
                        </a>

                        {{-- Tab 4: Notulen --}}
                        <a 
                            href="{{ route('tim.notulen.history') }}"
                            wire:navigate
                            class="flex flex-col items-center justify-center flex-1 h-full transition-colors {{ request()->routeIs('tim.notulen.*') ? 'text-[#3B71CA] dark:text-blue-400' : 'text-gray-400 dark:text-gray-500' }}"
                        >
                            @if(request()->routeIs('tim.notulen.*'))
                                <flux:icon.clipboard-document-list variant="solid" class="w-6 h-6" />
                            @else
                                <flux:icon.clipboard-document-list variant="outline" class="w-6 h-6" />
                            @endif
                            <span class="text-xs mt-1 font-medium">Notulen</span>
                        </a>

                        {{-- Tab 5: Akun --}}
                        <a 
                            href="{{ route('tim.akun') }}"
                            wire:navigate
                            class="flex flex-col items-center justify-center flex-1 h-full transition-colors {{ request()->routeIs('tim.profil') || request()->routeIs('tim.tambah-email') || request()->routeIs('tim.akun') ? 'text-[#3B71CA] dark:text-blue-400' : 'text-gray-400 dark:text-gray-500' }}"
                        >
                            @if(request()->routeIs('tim.profil') || request()->routeIs('tim.tambah-email') || request()->routeIs('tim.akun'))
                                <flux:icon.user variant="solid" class="w-6 h-6" />
                            @else
                                <flux:icon.user variant="outline" class="w-6 h-6" />
                            @endif
                            <span class="text-xs mt-1 font-medium">Akun</span>
                        </a>
                    </div>
                </div>
            </nav>
        @endif

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        {{-- Notulen Reminder Popup (untuk tim yang dapat tugas notulensi) --}}
        @if (!auth()->user()?->hasRole('admin'))
            <livewire:notulen-reminder-popup />
        @endif

        @fluxScripts
    </body>
</html>
