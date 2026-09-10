@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gray-50 dark:bg-zinc-900">
        {{-- Main Container with flex column --}}
        <div class="flex flex-col min-h-screen">
            {{-- Header / Top Bar (Simplified) --}}
            <header class="bg-white dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 px-4 py-3 sticky top-0 z-20">
                <div class="flex justify-between items-center">
                    {{-- Left: Logo + Brand Name --}}
                    <div class="flex items-center gap-3">
                        <img src="/favicon.svg" alt="Giliran" class="h-8 w-8">
                        <span class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Giliran</span>
                    </div>

                    {{-- Right: Notifications Only --}}
                    <div class="flex items-center">
                        <livewire:notifikasi-dropdown />
                    </div>
                </div>
            </header>

            {{-- Content Area (scrollable) --}}
            <main class="flex-1 overflow-y-auto pb-20 px-4 py-6">
                {{ $slot }}
            </main>

            {{-- Bottom Navigation Bar (fixed) --}}
            <nav class="fixed bottom-0 left-0 right-0 bg-white dark:bg-zinc-800 border-t border-zinc-200 dark:border-zinc-700 shadow-[0_-2px_10px_rgba(0,0,0,0.05)] safe-area-bottom z-30">
                {{-- Centered container for tablet optimization --}}
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

                    {{-- Tab 4: Notulen (NEW) --}}
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
        </div>

        {{-- Footer (visible on scroll, above bottom nav) --}}
        <div class="pb-20 px-4">
            <div class="border-t border-zinc-200/60 dark:border-zinc-800 bg-white/50 dark:bg-zinc-900/50 py-3.5 px-4 text-xs text-zinc-500 text-center space-y-1">
                <div>2026 © <strong class="font-semibold text-zinc-700 dark:text-zinc-300">Giliran</strong> — PT Inovindo Digital Media</div>
                <div>
                    <span>Developed by </span>
                    <a href="https://obikusakabe.github.io/MyPortfolio/" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#3B71CA] dark:text-blue-400">Roby Rachmat Firdaus</a>
                </div>
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
