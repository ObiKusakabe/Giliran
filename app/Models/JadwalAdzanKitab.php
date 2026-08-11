<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $personil_id
 * @property Carbon $tanggal
 * @property string $waktu_sholat -- fajr|dhuhr|asr|maghrib|isha (MVP: dhuhr & asr)
 * @property string $jenis_tugas -- adzan|kajian
 * @property string $status_konfirmasi -- menunggu|siap|berhalangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class JadwalAdzanKitab extends Model
{
    protected $table = 'jadwal_adzan_kitab';

    protected $fillable = [
        'personil_id',
        'tanggal',
        'waktu_sholat',
        'jenis_tugas',
        'status_konfirmasi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    /** @return BelongsTo<Personil, $this> */
    public function personil(): BelongsTo
    {
        return $this->belongsTo(Personil::class, 'personil_id');
    }
}
