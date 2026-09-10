<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tim_id
 * @property int $ruangan_id
 * @property Carbon $tanggal
 * @property int $expected_attendance
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AlokasiRuangan extends Model
{
    protected $table = 'alokasi_ruangan';

    protected $fillable = [
        'tim_id',
        'ruangan_id',
        'tanggal',
        'expected_attendance',
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

    /** @return BelongsTo<Ruangan, $this> */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    /**
     * FASE 4.4: Calculate capacity utilization percentage.
     *
     * @return float Percentage (0-100+), can exceed 100 if overbooked
     */
    public function utilizationPercentage(): float
    {
        if (! $this->ruangan || $this->ruangan->kapasitas === 0) {
            return 0;
        }

        return round(($this->expected_attendance / $this->ruangan->kapasitas) * 100, 1);
    }

    /**
     * FASE 4.4: Check if room is over capacity.
     */
    public function isOverCapacity(): bool
    {
        return $this->expected_attendance > ($this->ruangan->kapasitas ?? 0);
    }

    /**
     * FASE 4.4: Check if room utilization is low (< 50%).
     */
    public function isUnderutilized(): bool
    {
        return $this->utilizationPercentage() < 50;
    }
}
