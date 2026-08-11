<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tim_id
 * @property int $personil_id
 * @property Carbon $tanggal
 * @property string $sesi -- pagi|sore
 * @property string $status_konfirmasi -- menunggu|siap|berhalangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class JadwalBriefing extends Model
{
    protected $table = 'jadwal_briefing';

    protected $fillable = [
        'tim_id',
        'personil_id',
        'tanggal',
        'sesi',
        'status_konfirmasi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    /** @return BelongsTo<Tim, $this> */
    public function tim(): BelongsTo
    {
        return $this->belongsTo(Tim::class, 'tim_id');
    }

    /** @return BelongsTo<Personil, $this> */
    public function personil(): BelongsTo
    {
        return $this->belongsTo(Personil::class, 'personil_id');
    }
}
