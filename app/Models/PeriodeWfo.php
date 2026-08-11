<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property string|null $keterangan
 * @property string $status -- aktif|nonaktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PeriodeWfo extends Model
{
    protected $table = 'periode_wfo';

    protected $fillable = [
        'tanggal_mulai',
        'tanggal_selesai',
        'keterangan',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    /** @return HasMany<JadwalWfo, $this> */
    public function jadwalWfo(): HasMany
    {
        return $this->hasMany(JadwalWfo::class, 'periode_wfo_id');
    }

    public function isAktif(): bool
    {
        return $this->status === 'aktif';
    }
}
