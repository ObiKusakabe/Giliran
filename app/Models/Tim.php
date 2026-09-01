<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama_tim
 * @property string|null $keterangan
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Tim extends Model
{
    use SoftDeletes;

    protected $table = 'tim';

    protected $fillable = [
        'nama_tim',
        'keterangan',
        'status',
    ];

    /** @return HasMany<Personil, $this> */
    public function personil(): HasMany
    {
        return $this->hasMany(Personil::class, 'tim_id');
    }

    /** @return HasMany<JadwalWfo, $this> */
    public function jadwalWfo(): HasMany
    {
        return $this->hasMany(JadwalWfo::class, 'tim_id');
    }

    /** @return HasMany<AlokasiRuangan, $this> */
    public function alokasiRuangan(): HasMany
    {
        return $this->hasMany(AlokasiRuangan::class, 'tim_id');
    }

    /** @return HasMany<JadwalBriefing, $this> */
    public function jadwalBriefing(): HasMany
    {
        return $this->hasMany(JadwalBriefing::class, 'tim_id');
    }

    /** @return HasOne<User, $this> */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'tim_id');
    }

    /** Check apakah tim sudah memiliki akun login */
    public function hasAccount(): bool
    {
        return $this->user()->exists();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Scope untuk filter tim yang aktif.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope untuk filter tim yang inactive.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Check apakah tim active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
