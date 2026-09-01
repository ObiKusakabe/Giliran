<?php

namespace App\Livewire;

use App\Models\Notifikasi;
use App\Models\Personil;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotifikasiDropdown extends Component
{
    #[Computed]
    public function notifikasi()
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        $query = Notifikasi::query()->orderByDesc('terkirim_pada');

        if ($user->isTim()) {
            $personilIds = Personil::where('tim_id', $user->tim_id)->pluck('id');
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

        return $query->take(5)->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        $user = auth()->user();

        if (! $user) {
            return 0;
        }

        $query = Notifikasi::query()->where('dibaca', false);

        if ($user->isTim()) {
            $personilIds = Personil::where('tim_id', $user->tim_id)->pluck('id');
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

        return $query->count();
    }

    public function tandaiDibaca(int $id): void
    {
        $notif = Notifikasi::find($id);

        if ($notif) {
            $notif->update(['dibaca' => true]);
            unset($this->notifikasi, $this->unreadCount);
        }
    }

    public function markAllRead(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $query = Notifikasi::query()->where('dibaca', false);

        if ($user->isTim()) {
            $personilIds = Personil::where('tim_id', $user->tim_id)->pluck('id');
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

        $query->update(['dibaca' => true]);
        unset($this->notifikasi, $this->unreadCount);

        Flux::toast(variant: 'success', text: 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function render()
    {
        return view('livewire.notifikasi-dropdown');
    }
}
