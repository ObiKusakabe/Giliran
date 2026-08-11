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

    /** @return BelongsTo<Ruangan, $this> */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }
}
