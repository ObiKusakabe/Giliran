<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama_ruangan
 * @property int $kapasitas
 * @property string $status -- tersedia|tidak_tersedia
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Ruangan extends Model
{
    use SoftDeletes;

    protected $table = 'ruangan';

    protected $fillable = [
        'nama_ruangan',
        'kapasitas',
        'status',
    ];

    /** @return HasMany<AlokasiRuangan, $this> */
    public function alokasiRuangan(): HasMany
    {
        return $this->hasMany(AlokasiRuangan::class, 'ruangan_id');
    }

    public function isTersedia(): bool
    {
        return $this->status === 'tersedia';
    }
}
