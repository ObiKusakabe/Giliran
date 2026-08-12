@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-900">

        {{-- ─── Sidebar (desktop: collapsible to icons | mobile: off-canvas) ─── --}}
        <div
            x-data="{ open: false, collapsed: localStorage.getItem('sidebar-collapsed') === 'true' }"
            x-init="$watch('collapsed', v => localStorage.setItem('sidebar-collapsed', v))"
            class="flex min-h-screen"
        >
            {{-- Mobile overlay --}}
            <div
                x-show="open"
                x-transition.opacity
                @click="open = false"
                class="fixed inset-0 z-20 bg-black/60 lg:hidden"
            ></div>

            {{-- Sidebar panel --}}
            <aside
                :class="collapsed ? 'w-[60px]' : 'w-64'"
                class="fixed inset-y-0 left-0 z-30 flex flex-col bg-zinc-950 border-r border-zinc-800
                       transition-[width] duration-200 -translate-x-full lg:translate-x-0 overflow-hidden"
                :class="open ? '!translate-x-0' : ''"
            >
                {{-- Header: Logo + Title + Toggle button --}}
                <div class="flex items-center h-14 px-3 gap-2 flex-shrink-0">

                    {{-- Saat EXPANDED: logo biasa di kiri --}}
                    <a x-show="!collapsed"
                       href="{{ route('admin.dashboard') }}"
                       wire:navigate
                       class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-brand text-white text-xs font-bold">
                        ID
                    </a>

                    {{-- Saat COLLAPSED: logo area yg jadi tombol toggle (hover → swap ke chevron kanan) --}}
                    <div x-show="collapsed"
                         x-data="{ hovered: false }"
                         @mouseenter="hovered = true"
                         @mouseleave="hovered = false"
                         @click="collapsed = false; $dispatch('sidebar-toggled')"
                         class="relative flex h-8 w-8 flex-shrink-0 mx-auto cursor-pointer items-center justify-center rounded-md transition-colors"
                         :class="hovered ? 'bg-zinc-800' : 'bg-brand'"
                         title="Buka sidebar"
                    >
                        {{-- Standby: logo ID --}}
                        <span x-show="!hovered" class="text-white text-xs font-bold select-none">ID</span>
                        {{-- Hover: chevron kanan saja, tanpa rectangle --}}
                        <svg x-show="hovered" class="h-4 w-4 text-zinc-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>

                    {{-- Title (hanya saat expanded) --}}
                    <span x-show="!collapsed"
                          class="text-sm font-semibold text-zinc-100 truncate flex-1">
                        Giliran
                    </span>

                    {{-- Tombol toggle tutup (hanya saat expanded, desktop) --}}
                    <button
                        x-show="!collapsed"
                        x-data="{ hovered: false }"
                        @mouseenter="hovered = true"
                        @mouseleave="hovered = false"
                        @click="collapsed = true; $dispatch('sidebar-toggled')"
                        title="Tutup sidebar"
                        class="hidden lg:flex ml-auto h-8 w-8 flex-shrink-0 items-center justify-center
                               rounded-lg text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800
                               transition-colors"
                    >
                        {{-- Standby: rectangle tanpa chevron --}}
                        <svg x-show="!hovered" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2.5"/>
                            <path d="M9 3v18"/>
                        </svg>
                        {{-- Hover: rectangle + chevron kiri --}}
                        <svg x-show="hovered" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2.5"/>
                            <path d="M9 3v18"/>
                            <path d="M14 9l-3 3 3 3"/>
                        </svg>
                    </button>

                    {{-- Close button (mobile only) --}}
                    <button @click="open = false" class="lg:hidden ml-auto p-1 text-zinc-400 hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Nav items --}}
                <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 px-2 flex flex-col gap-0.5">
                    @php
                    $navItems = [
                        ['route' => 'admin.dashboard',      'icon' => 'chart-bar',              'label' => 'Dashboard'],
                        ['separator' => true],
                        ['route' => 'admin.tim',            'icon' => 'users',                  'label' => 'Tim'],
                        ['route' => 'admin.personil',       'icon' => 'user',                   'label' => 'Personil'],
                        ['route' => 'admin.ruangan',        'icon' => 'home-modern',             'label' => 'Ruangan'],
                        ['separator' => true],
                        ['route' => 'admin.periode-wfo',    'icon' => 'calendar-days',          'label' => 'Periode WFO'],
                        ['route' => 'admin.jadwal-wfo',     'icon' => 'calendar-days',          'label' => 'Jadwal WFO'],
                        ['route' => 'admin.generate-jadwal','icon' => 'sparkles',               'label' => 'Generate Jadwal'],
                        ['route' => 'admin.kalender',       'icon' => 'calendar-days',          'label' => 'Kalender'],
                        ['separator' => true],
                        ['route' => 'admin.export',         'icon' => 'arrow-down-tray',        'label' => 'Export'],
                    ];
                    @endphp

                    @foreach ($navItems as $item)
                        @if (isset($item['separator']))
                            <div class="my-1 border-t border-zinc-800"></div>
                        @else
                            @php $isCurrent = request()->routeIs($item['route']); @endphp
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                title="{{ $item['label'] }}"
                                class="group flex items-center gap-3 rounded-md px-2 py-2 text-sm transition-colors
                                       {{ $isCurrent
                                           ? 'bg-zinc-800 text-white font-medium'
                                           : 'text-zinc-400 hover:bg-zinc-800 hover:text-white' }}"
                                :class="collapsed ? 'justify-center' : ''"
                            >
                                <flux:icon
                                    icon="{{ $item['icon'] }}"
                                    class="h-4 w-4 flex-shrink-0 {{ $isCurrent ? 'text-brand' : 'text-zinc-400 group-hover:text-zinc-200' }}"
                                />
                                <span x-show="!collapsed" x-transition.opacity class="truncate">
                                    {{ $item['label'] }}
                                </span>
                            </a>
                        @endif
                    @endforeach
                </nav>

                {{-- User menu --}}
                <div class="border-t border-zinc-800 p-2 flex-shrink-0">
                    <div x-show="!collapsed" x-transition.opacity>
                        <flux:dropdown position="top" align="start" class="w-full">
                            <flux:button variant="ghost" class="w-full justify-start gap-2 text-zinc-300 hover:text-white hover:bg-zinc-800">
                                <flux:avatar :name="auth()->user()->name" size="xs" />
                                <span class="truncate text-sm">{{ auth()->user()->name }}</span>
                            </flux:button>
                            <flux:menu>
                                <flux:menu.item icon="bell" :href="route('notifikasi.index')" wire:navigate>Notifikasi</flux:menu.item>
                                <flux:menu.separator />
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">Keluar</flux:menu.item>
                                </form>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                    <div x-show="collapsed" x-transition.opacity class="flex justify-center">
                        <flux:dropdown position="top" align="start">
                            <button class="flex h-8 w-8 items-center justify-center rounded-full overflow-hidden">
                                <flux:avatar :name="auth()->user()->name" size="xs" />
                            </button>
                            <flux:menu>
                                <flux:menu.item icon="bell" :href="route('notifikasi.index')" wire:navigate>Notifikasi</flux:menu.item>
                                <flux:menu.separator />
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">Keluar</flux:menu.item>
                                </form>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>

            </aside>

            {{-- ─── Main content area ─── --}}
            <div
                :class="collapsed ? 'lg:pl-[60px]' : 'lg:pl-64'"
                class="flex-1 flex flex-col min-w-0 transition-all duration-200"
            >
                {{-- Mobile topbar --}}
                <header class="lg:hidden sticky top-0 z-10 flex h-14 items-center border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 gap-3">
                    <button @click="open = true" class="p-1.5 rounded-md text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <span class="font-semibold text-sm flex-1">Giliran</span>
                    <flux:dropdown position="bottom" align="end">
                        <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                        <flux:menu>
                            <flux:menu.item icon="bell" :href="route('notifikasi.index')" wire:navigate>Notifikasi</flux:menu.item>
                            <flux:menu.separator />
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">Keluar</flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                </header>

                {{-- Page content --}}
                <main class="flex-1 p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
