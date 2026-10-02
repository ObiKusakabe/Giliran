<?php

use App\Models\Notifikasi;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifikasi')] #[Layout('layouts.app', ['breadcrumbs' => [['label' => 'Notifikasi']]])] class extends Component {
    use WithPagination;

    #[Computed]
    public function notifikasiList()
    {
        $user  = auth()->user();
        $query = Notifikasi::query()->orderByDesc('terkirim_pada');

        /**
         * Scoping query per role.
         * Role 'personil' sudah dihapus — sekarang cukup tim dan admin.
         * Tim melihat notifikasi yang ditujukan ke user_id mereka
         * ATAU notifikasi tentang personil dalam tim mereka (personil_id IN tim).
         */
        if ($user->isTim()) {
            // Notifikasi ke akun tim ini ATAU tentang personil dalam timnya
            $personilIds = \App\Models\Personil::where('tim_id', $user->tim_id)->pluck('id');
            $query->where(function ($q) use ($user, $personilIds) {
                $q->where('user_id', $user->id)
                    ->orWhereIn('personil_id', $personilIds);
            });
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
            $personilIds = \App\Models\Personil::where('tim_id', $user->tim_id)->pluck('id');
            $query->where(function ($q) use ($user, $personilIds) {
                $q->where('user_id', $user->id)
                    ->orWhereIn('personil_id', $personilIds);
            });
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

<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div class="flex items-center gap-3">
            <flux:button 
                variant="ghost" 
                size="sm" 
                icon="arrow-left" 
                href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('tim.jadwal') }}" 
                wire:navigate 
                title="Kembali"
                class="sm:hidden"
            />
            <div>
                <flux:heading size="xl" class="flex items-center gap-2.5">
                    <span>Semua Notifikasi</span>
                    @if ($this->jumlahBelumDibaca > 0)
                        <span class="px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 text-xs font-semibold">
                            {{ $this->jumlahBelumDibaca }} baru
                        </span>
                    @endif
                </flux:heading>
                <flux:subheading class="text-xs sm:text-sm mt-0.5">Daftar riwayat pemberitahuan jadwal, alokasi ruangan, dan tugas Anda</flux:subheading>
            </div>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto">
            @if ($this->jumlahBelumDibaca > 0)
                <flux:button size="sm" variant="subtle" wire:click="tandaiSemuaDibaca" icon="check">
                    Tandai Semua Dibaca
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Notification List --}}
    @if ($this->notifikasiList->isEmpty())
        <div class="py-12 text-center bg-white dark:bg-zinc-800/50 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-8 shadow-xs">
            <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800 text-zinc-400 dark:text-zinc-500">
                <flux:icon icon="bell-slash" class="size-7" />
            </div>
            <flux:heading size="md">Tidak ada notifikasi</flux:heading>
            <flux:subheading class="mt-1 max-w-sm mx-auto text-xs">Kamu belum memiliki riwayat notifikasi apapun saat ini.</flux:subheading>
        </div>
    @else
        <div class="space-y-2.5">
            @foreach ($this->notifikasiList as $notif)
                <div
                    wire:key="notif-page-{{ $notif->id }}"
                    wire:click="tandaiDibaca({{ $notif->id }})"
                    class="flex items-start gap-3.5 p-4 rounded-xl border transition-all cursor-pointer shadow-xs {{ ! $notif->dibaca ? 'bg-blue-50/50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-900/50 hover:border-blue-300 dark:hover:border-blue-800' : 'bg-white dark:bg-zinc-800/60 border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600' }}"
                >
                    {{-- Icon Tipe --}}
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg mt-0.5 {{ ! $notif->dibaca ? 'bg-blue-600 text-white shadow-xs' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-300' }}">
                        @if ($notif->tipe === 'pengganti')
                            <flux:icon icon="arrows-right-left" class="size-4.5" />
                        @elseif ($notif->tipe === 'reminder')
                            <flux:icon icon="clock" class="size-4.5" />
                        @elseif ($notif->tipe === 'jadwal')
                            <flux:icon icon="calendar-days" class="size-4.5" />
                        @else
                            <flux:icon icon="bell" class="size-4.5" />
                        @endif
                    </div>

                    {{-- Konten --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider text-[10px]">
                                {{ $notif->tipe ?: 'Umum' }}
                            </span>
                            <span class="text-[11px] text-zinc-400 dark:text-zinc-500 shrink-0">
                                {{ ($notif->terkirim_pada ?? $notif->created_at)->diffForHumans() }}
                            </span>
                        </div>
                        <p class="text-sm text-zinc-900 dark:text-zinc-100 mt-1 leading-relaxed {{ ! $notif->dibaca ? 'font-medium' : '' }}">
                            {{ $notif->pesan }}
                        </p>
                        <p class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1.5 flex items-center gap-1">
                            <flux:icon icon="calendar" class="size-3" />
                            <span>{{ ($notif->terkirim_pada ?? $notif->created_at)->translatedFormat('l, d F Y H:i') }}</span>
                        </p>
                    </div>

                    {{-- Unread Dot Indicator --}}
                    @if (! $notif->dibaca)
                        <span class="size-2.5 shrink-0 rounded-full bg-blue-600 dark:bg-blue-400 mt-2 ring-4 ring-blue-100 dark:ring-blue-900/40"></span>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($this->notifikasiList->hasPages())
            <div class="pt-4">
                {{ $this->notifikasiList->links() }}
            </div>
        @endif
    @endif
</div>
