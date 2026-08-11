<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama_tim
 * @property string|null $keterangan
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
}
