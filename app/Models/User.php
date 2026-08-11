<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int|null $personil_id
 * @property int|null $tim_id
 * @property string|null $username
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string $role -- admin|personil|tim
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $fillable = [
        'personil_id',
        'tim_id',
        'username',
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Relasi ────────────────────────────────────────────────────

    /** @return BelongsTo<Personil, $this> */
    public function personil(): BelongsTo
    {
        return $this->belongsTo(Personil::class, 'personil_id');
    }

    /** @return BelongsTo<Tim, $this> */
    public function tim(): BelongsTo
    {
        return $this->belongsTo(Tim::class, 'tim_id');
    }

    /** @return HasMany<Notifikasi, $this> */
    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'user_id');
    }

    // ── Role helpers ──────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPersonil(): bool
    {
        return $this->role === 'personil';
    }

    public function isTim(): bool
    {
        return $this->role === 'tim';
    }

    // ── Misc ──────────────────────────────────────────────────────

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
