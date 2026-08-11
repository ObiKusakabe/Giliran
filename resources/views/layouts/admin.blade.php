@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-900">
        <flux:sidebar
            sticky
            collapsible="mobile"
            class="w-64 border-e border-zinc-200 bg-zinc-950 text-zinc-100 dark:border-zinc-700"
        >
            <flux:sidebar.header>
                {{-- Logo Inovindo + nama aplikasi --}}
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-center gap-2 px-2 py-1">
                    <span class="flex h-8 w-8 items-center justify-center rounded-md bg-brand text-white text-xs font-bold">
                        ID
                    </span>
                    <span class="text-sm font-semibold text-zinc-100 truncate">
                        Jadwal Internal
                    </span>
                </a>
                <flux:sidebar.collapse class="lg:hidden text-zinc-400" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="gap-0.5 px-2">
                <flux:sidebar.item
                    icon="chart-bar"
                    :href="route('admin.dashboard')"
                    :current="request()->routeIs('admin.dashboard')"
                    wire:navigate
                    class="text-zinc-300 hover:text-white hover:bg-zinc-800"
                >
                    Dashboard
                </flux:sidebar.item>

                <flux:separator class="my-1 border-zinc-800" />

                <flux:sidebar.item
                    icon="users"
                    :href="route('admin.tim')"
                    :current="request()->routeIs('admin.tim')"
                    wire:navigate
                    class="text-zinc-300 hover:text-white hover:bg-zinc-800"
                >
                    Tim
                </flux:sidebar.item>

                <flux:sidebar.item
                    icon="user"
                    :href="route('admin.personil')"
                    :current="request()->routeIs('admin.personil')"
                    wire:navigate
                    class="text-zinc-300 hover:text-white hover:bg-zinc-800"
                >
                    Personil
                </flux:sidebar.item>

                <flux:sidebar.item
                    icon="home-modern"
                    :href="route('admin.ruangan')"
                    :current="request()->routeIs('admin.ruangan')"
                    wire:navigate
                    class="text-zinc-300 hover:text-white hover:bg-zinc-800"
                >
                    Ruangan
                </flux:sidebar.item>

                <flux:separator class="my-1 border-zinc-800" />

                <flux:sidebar.item
                    icon="calendar-days"
                    :href="route('admin.periode-wfo')"
                    :current="request()->routeIs('admin.periode-wfo')"
                    wire:navigate
                    class="text-zinc-300 hover:text-white hover:bg-zinc-800"
                >
                    Periode WFO
                </flux:sidebar.item>

                <flux:sidebar.item
                    icon="calendar-days"
                    :href="route('admin.jadwal-wfo')"
                    :current="request()->routeIs('admin.jadwal-wfo')"
                    wire:navigate
                    class="text-zinc-300 hover:text-white hover:bg-zinc-800"
                >
                    Jadwal WFO
                </flux:sidebar.item>

                <flux:sidebar.item
                    icon="sparkles"
                    :href="route('admin.generate-jadwal')"
                    :current="request()->routeIs('admin.generate-jadwal')"
                    wire:navigate
                    class="text-zinc-300 hover:text-white hover:bg-zinc-800"
                >
                    Generate Jadwal
                </flux:sidebar.item>

                <flux:sidebar.item
                    icon="calendar-days"
                    :href="route('admin.kalender')"
                    :current="request()->routeIs('admin.kalender')"
                    wire:navigate
                    class="text-zinc-300 hover:text-white hover:bg-zinc-800"
                >
                    Kalender
                </flux:sidebar.item>

                <flux:separator class="my-1 border-zinc-800" />

                <flux:sidebar.item
                    icon="arrow-down-tray"
                    :href="route('admin.export')"
                    :current="request()->routeIs('admin.export')"
                    wire:navigate
                    class="text-zinc-300 hover:text-white hover:bg-zinc-800"
                >
                    Export
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- User menu di bagian bawah sidebar --}}
            <div class="p-2 border-t border-zinc-800">
                <flux:dropdown position="top" align="start" class="w-full">
                    <flux:button variant="ghost" class="w-full justify-start gap-2 text-zinc-300 hover:text-white">
                        <flux:avatar :name="auth()->user()->name" size="xs" />
                        <span class="truncate text-sm">{{ auth()->user()->name }}</span>
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item
                            icon="bell"
                            :href="route('notifikasi.index')"
                            wire:navigate
                        >
                            Notifikasi
                        </flux:menu.item>
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <flux:menu.item
                                as="button"
                                type="submit"
                                icon="arrow-right-start-on-rectangle"
                                class="w-full cursor-pointer"
                            >
                                Keluar
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </flux:sidebar>

        {{-- Mobile topbar --}}
        <flux:header class="lg:hidden border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <span class="font-semibold text-sm">Jadwal Internal</span>
            <flux:spacer />
            <flux:dropdown position="bottom" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                <flux:menu>
                    <flux:menu.item icon="bell" :href="route('notifikasi.index')" wire:navigate>Notifikasi</flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                            Keluar
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{-- Konten utama --}}
        <flux:main class="p-6">
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
