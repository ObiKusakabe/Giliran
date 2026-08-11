<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tim_id
 * @property string $nama
 * @property string|null $no_hp
 * @property string $status -- aktif|nonaktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Personil extends Model
{
    use SoftDeletes;

    protected $table = 'personil';

    protected $fillable = [
        'tim_id',
        'nama',
        'no_hp',
        'status',
    ];

    /** @return BelongsTo<Tim, $this> */
    public function tim(): BelongsTo
    {
        return $this->belongsTo(Tim::class, 'tim_id');
    }

    /** @return HasOne<User, $this> */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'personil_id');
    }

    /** @return HasMany<JadwalAdzanKitab, $this> */
    public function jadwalAdzanKitab(): HasMany
    {
        return $this->hasMany(JadwalAdzanKitab::class, 'personil_id');
    }

    /** @return HasMany<JadwalBriefing, $this> */
    public function jadwalBriefing(): HasMany
    {
        return $this->hasMany(JadwalBriefing::class, 'personil_id');
    }

    /** @return HasMany<Notifikasi, $this> */
    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'personil_id');
    }

    public function isAktif(): bool
    {
        return $this->status === 'aktif';
    }
}
