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
        'moderator_id',
        'doa_id',
        'tanggal',
        'sesi',
        'status_konfirmasi',
        'is_notulen',
        'alasan_berhalangan',
        'is_switched',
        'original_personil_id',
        'switch_reason',
        'switched_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

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

    /** FASE 4.3: @return BelongsTo<Personil, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(Personil::class, 'moderator_id');
    }

    /** FASE 4.3: @return BelongsTo<Personil, $this> */
    public function doa(): BelongsTo
    {
        return $this->belongsTo(Personil::class, 'doa_id');
    }

    /** @return BelongsTo<Personil, $this> */
    public function originalPersonil(): BelongsTo
    {
        return $this->belongsTo(Personil::class, 'original_personil_id');
    }
}
