<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $personil_id -- diisi untuk notif ke role personil
 * @property int|null $user_id -- diisi untuk notif ke role admin atau tim
 * @property string $tipe -- jadwal|pengganti|reminder
 * @property string $pesan
 * @property int|null $data_id -- ID jadwal terkait
 * @property bool $dibaca
 * @property Carbon $terkirim_pada
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Notifikasi extends Model
{
    protected $table = 'notifikasi';

    protected $fillable = [
        'personil_id',
        'user_id',
        'tipe',
        'pesan',
        'data_id',
        'dibaca',
        'terkirim_pada',
    ];

    protected function casts(): array
    {
        return [
            'dibaca' => 'boolean',
            'terkirim_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<Personil, $this> */
    public function personil(): BelongsTo
    {
        return $this->belongsTo(Personil::class, 'personil_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
