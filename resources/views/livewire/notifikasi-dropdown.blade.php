<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    {{-- Bell Trigger Button --}}
    <button
        type="button"
        @click="open = !open"
        class="relative flex h-10 w-10 items-center justify-center rounded-lg text-zinc-500 hover:text-zinc-800 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:text-zinc-200 dark:hover:bg-zinc-800 transition-colors focus:outline-none"
        title="Notifikasi"
        aria-label="Buka Notifikasi"
    >
        <flux:icon icon="bell" class="size-5" />

        @if ($this->unreadCount > 0)
            <span class="absolute 1 top-1.5 right-1.5 flex h-4 min-w-4 px-1 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white shadow-sm ring-2 ring-white dark:ring-zinc-900 animate-pulse">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-1 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 scale-95"
        x-cloak
        class="absolute right-0 mt-2 w-80 sm:w-96 rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl border border-zinc-200 dark:border-zinc-800 z-[200] overflow-hidden"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/50">
            <div class="flex items-center gap-2">
                <span class="font-semibold text-sm text-zinc-900 dark:text-white">Notifikasi</span>
                @if ($this->unreadCount > 0)
                    <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 text-xs font-medium">
                        {{ $this->unreadCount }} baru
                    </span>
                @endif
            </div>

            @if ($this->unreadCount > 0)
                <button
                    type="button"
                    wire:click="markAllRead"
                    class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                >
                    Tandai dibaca
                </button>
            @endif
        </div>

        {{-- Notifications List --}}
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800 max-h-80 overflow-y-auto">
            @forelse ($this->notifikasi as $item)
                <div
                    wire:key="notif-{{ $item->id }}"
                    wire:click="tandaiDibaca({{ $item->id }})"
                    class="flex items-start gap-3 p-3.5 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 transition cursor-pointer {{ ! $item->dibaca ? 'bg-blue-50/40 dark:bg-blue-950/20' : '' }}"
                >
                    {{-- Icon Tipe --}}
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ ! $item->dibaca ? 'bg-blue-600 text-white' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' }} mt-0.5">
                        @if ($item->tipe === 'jadwal')
                            <flux:icon icon="calendar-days" class="size-4" />
                        @elseif ($item->tipe === 'pengganti')
                            <flux:icon icon="arrows-right-left" class="size-4" />
                        @else
                            <flux:icon icon="clock" class="size-4" />
                        @endif
                    </div>

                    {{-- Konten Pesan --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-zinc-800 dark:text-zinc-200 leading-snug line-clamp-2 {{ ! $item->dibaca ? 'font-medium' : '' }}">
                            {{ $item->pesan }}
                        </p>
                        <span class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1 block">
                            {{ ($item->terkirim_pada ?? $item->created_at)->translatedFormat('l, d F Y') }}
                        </span>
                    </div>

                    {{-- Unread Dot --}}
                    @if (! $item->dibaca)
                        <span class="size-2 shrink-0 rounded-full bg-blue-600 dark:bg-blue-400 mt-2"></span>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center">
                    <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400 dark:text-zinc-500">
                        <flux:icon icon="bell-slash" class="size-6" />
                    </div>
                    <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Belum ada notifikasi</p>
                    <p class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-0.5">Notifikasi penugasan jadwal akan muncul di sini.</p>
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        <div class="p-2.5 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/50 text-center">
            <a
                href="{{ route('notifikasi.index') }}"
                wire:navigate
                @click="open = false"
                class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 inline-flex items-center gap-1.5"
            >
                <span>Lihat Semua Notifikasi</span>
                <flux:icon icon="chevron-right" class="size-3" />
            </a>
        </div>
    </div>
</div>
