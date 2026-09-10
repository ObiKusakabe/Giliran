<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $periode_wfo_id
 * @property int $tim_id
 * @property string $hari -- senin|selasa|rabu|kamis|jumat|sabtu
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class JadwalWfo extends Model
{
    protected $table = 'jadwal_wfo';

    protected $fillable = [
        'periode_wfo_id',
        'tim_id',
        'hari',
    ];

    /**
     * Boot method - Add global scope for tim isolation.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('tim_isolation', function ($builder) {
            $user = auth()->user();

            if ($user && $user->isTim()) {
                $builder->where('tim_id', $user->tim_id);
            }
        });
    }

    /** @return BelongsTo<PeriodeWfo, $this> */
    public function periodeWfo(): BelongsTo
    {
        return $this->belongsTo(PeriodeWfo::class, 'periode_wfo_id');
    }

    /** @return BelongsTo<Tim, $this> */
    public function tim(): BelongsTo
    {
        return $this->belongsTo(Tim::class, 'tim_id');
    }
}
