<?php

use App\Models\Notifikasi;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifikasi')] #[Layout('layouts.auth')] class extends Component {
    use WithPagination;

    #[Computed]
    public function notifikasiList()
    {
        $user  = auth()->user();
        $query = Notifikasi::query()->orderByDesc('terkirim_pada');

        /**
         * Scoping query per role — §5.2 aturan scoping notifikasi.
         * JANGAN query tanpa filter (bisa lihat notifikasi semua personil).
         */
        if ($user->isPersonil()) {
            // Notifikasi untuk akun personil ini
            $query->where('personil_id', $user->personil?->id ?? 0);
        } elseif ($user->isTim()) {
            // Notifikasi yang ditujukan ke akun tim ini
            $query->where('user_id', $user->id);
        } else {
            // Admin: notifikasi personal + broadcast umum
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('personil_id')->whereNull('user_id');
                    });
            });
        }

        return $query->paginate(20);
    }

    #[Computed]
    public function jumlahBelumDibaca(): int
    {
        return $this->notifikasiList->where('dibaca', false)->count();
    }

    public function tandaiDibaca(int $id): void
    {
        $notif = $this->cariNotifikasiMilikSendiri($id);

        if ($notif) {
            $notif->update(['dibaca' => true]);
            unset($this->notifikasiList);
        }
    }

    public function tandaiSemuaDibaca(): void
    {
        $this->notifikasiList->each(fn ($n) => $n->update(['dibaca' => true]));
        Flux::toast(variant: 'success', text: 'Semua notifikasi ditandai sudah dibaca.');
        unset($this->notifikasiList);
    }

    private function cariNotifikasiMilikSendiri(int $id): ?Notifikasi
    {
        $user  = auth()->user();
        $query = Notifikasi::where('id', $id);

        if ($user->isPersonil()) {
            $query->where('personil_id', $user->personil?->id ?? 0);
        } elseif ($user->isTim()) {
            $query->where('user_id', $user->id);
        } else {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('personil_id')->whereNull('user_id');
                    });
            });
        }

        return $query->first();
    }
}; ?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    {{-- Topbar --}}
    <header class="sticky top-0 z-10 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 h-14 flex items-center px-4 gap-3">
        <a
            href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : (auth()->user()->isPersonil() ? route('personil.jadwal-saya') : route('tim.ruangan')) }}"
            wire:navigate
            class="p-1.5 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700"
        >
            <flux:icon icon="arrow-left" class="h-5 w-5 text-zinc-500" />
        </a>
        <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 flex-1">Notifikasi</span>

        @if ($this->jumlahBelumDibaca > 0)
            <flux:button size="sm" variant="ghost" wire:click="tandaiSemuaDibaca">
                Tandai semua dibaca
            </flux:button>
        @endif
    </header>

    <div class="max-w-3xl mx-auto px-4 py-6">
        @if ($this->notifikasiList->isEmpty())
            <x-empty-state
                icon="bell"
                title="Tidak ada notifikasi"
                description="Kamu belum memiliki notifikasi apapun."
            />
        @else
            <div class="flex flex-col gap-1">
                @foreach ($this->notifikasiList as $notif)
                    <button
                        wire:click="tandaiDibaca({{ $notif->id }})"
                        class="w-full text-left flex items-start gap-3 px-4 py-3 rounded-lg transition-colors
                            {{ $notif->dibaca ? 'bg-white dark:bg-zinc-800' : 'bg-blue-50 dark:bg-blue-900/20' }}
                            hover:bg-zinc-100 dark:hover:bg-zinc-700/50"
                    >
                        {{-- Ikon tipe --}}
                        <div class="flex-shrink-0 mt-0.5">
                            @if ($notif->tipe === 'pengganti')
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                                    <flux:icon icon="arrow-path" class="h-4 w-4 text-amber-600" />
                                </span>
                            @elseif ($notif->tipe === 'reminder')
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                                    <flux:icon icon="clock" class="h-4 w-4 text-purple-600" />
                                </span>
                            @else
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                    <flux:icon icon="bell" class="h-4 w-4 text-blue-600" />
                                </span>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-zinc-900 dark:text-zinc-100 {{ $notif->dibaca ? '' : 'font-medium' }}">
                                {{ $notif->pesan }}
                            </p>
                            <p class="text-xs text-zinc-400 mt-0.5">
                                {{ $notif->terkirim_pada->diffForHumans() }}
                            </p>
                        </div>

                        {{-- Dot belum dibaca --}}
                        @if (! $notif->dibaca)
                            <span class="flex-shrink-0 mt-2 h-2 w-2 rounded-full bg-blue-500"></span>
                        @endif
                    </button>
                @endforeach
            </div>

            @if ($this->notifikasiList->hasPages())
                <div class="mt-4">
                    {{ $this->notifikasiList->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
