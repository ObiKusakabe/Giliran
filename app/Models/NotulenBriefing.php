<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class NotulenBriefing extends Model
{
    use HasFactory;

    protected $table = 'notulen_briefing';

    protected $fillable = [
        'tanggal',
        'sesi',
        'jenis_kehadiran',
        'tim_id',
        'user_id',
        'nama_notulen',
        'peserta',
        'catatan',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'viewed_by_admin',
        'admin_viewed_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'peserta' => 'array',
        'file_size' => 'integer',
        'viewed_by_admin' => 'boolean',
        'admin_viewed_at' => 'datetime',
    ];

    /**
     * Boot method - Add global scope for role-based visibility.
     */
    protected static function booted(): void
    {
        // Role-based visibility: All users (admin and tim) can see all notulen
        // No filtering needed - notulen are visible to everyone
    }

    /**
     * Relasi ke Tim yang terlibat (many-to-many via pivot).
     */
    public function timYangTerlibat(): BelongsToMany
    {
        return $this->belongsToMany(Tim::class, 'notulen_briefing_tim')
            ->withPivot('is_creator')
            ->withTimestamps();
    }

    /**
     * FASE 4.2: Relasi ke Personil WFH peserta (many-to-many).
     */
    public function pesertaWfh(): BelongsToMany
    {
        return $this->belongsToMany(Personil::class, 'notulen_wfh_peserta')
            ->withPivot('is_creator')
            ->withTimestamps();
    }

    /**
     * Relasi ke Tim.
     */
    public function tim(): BelongsTo
    {
        return $this->belongsTo(Tim::class);
    }

    /**
     * Relasi ke User (notulen/pengisi).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get JadwalBriefing untuk tim creator berdasarkan tanggal dan sesi.
     * Returns the jadwal for the tim that created this notulen (is_creator = true).
     */
    public function jadwalBriefing()
    {
        $creatorTim = $this->timYangTerlibat()->wherePivot('is_creator', true)->first();
        
        if (!$creatorTim) {
            return null;
        }

        return JadwalBriefing::withoutGlobalScope('tim_isolation')
            ->where('tim_id', $creatorTim->id)
            ->where('tanggal', $this->tanggal)
            ->where('sesi', $this->sesi)
            ->where('is_notulen', true)
            ->with('personil')
            ->first();
    }

    /**
     * Get nama personil penulis notulen dari JadwalBriefing.
     */
    public function getPenulisNamaAttribute(): ?string
    {
        $jadwal = $this->jadwalBriefing();
        return $jadwal?->personil?->nama ?? null;
    }

    /**
     * Get formatted file size (e.g., "1.5 MB").
     */
    public function getFormattedFileSizeAttribute(): ?string
    {
        if (! $this->file_size) {
            return null;
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Get full URL for file download.
     */
    public function getFileUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::url($this->file_path);
    }

    /**
     * Check if file exists in storage.
     */
    public function hasFile(): bool
    {
        return $this->file_path && Storage::disk('public')->exists($this->file_path);
    }

    /**
     * Delete file from storage.
     */
    public function deleteFile(): bool
    {
        if ($this->hasFile()) {
            return Storage::disk('public')->delete($this->file_path);
        }

        return false;
    }

    /**
     * Get jumlah peserta.
     */
    public function getJumlahPesertaAttribute(): int
    {
        return is_array($this->peserta) ? count($this->peserta) : 0;
    }

    /**
     * Scope: Filter by tim (visible to specific tim).
     */
    public function scopeVisibleToTim($query, int $timId)
    {
        return $query->whereHas('timYangTerlibat', fn ($q) => $q->where('tim_id', $timId));
    }

    /**
     * Check if notulen was created by specific tim.
     */
    public function isCreatedBy(int $timId): bool
    {
        return $this->timYangTerlibat()
            ->wherePivot('tim_id', $timId)
            ->wherePivot('is_creator', true)
            ->exists();
    }

    /**
     * Scope: Filter by tim.
     */
    public function scopeByTim($query, int $timId)
    {
        return $query->where('tim_id', $timId);
    }

    /**
     * Scope: Filter by tanggal range.
     */
    public function scopeBetweenDates($query, string $start, string $end)
    {
        return $query->whereBetween('tanggal', [$start, $end]);
    }

    /**
     * Scope: Today's notulen.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('tanggal', now()->toDateString());
    }

    /**
     * Scope: Ordered by latest.
     */
    public function scopeLatest($query)
    {
        return $query->orderByDesc('tanggal')->orderByDesc('created_at');
    }

    /**
     * FASE 4.2: Check if notulen is WFH type.
     */
    public function isWfh(): bool
    {
        return $this->jenis_kehadiran === 'wfh';
    }

    /**
     * FASE 4.2: Check if notulen is WFO type.
     */
    public function isWfo(): bool
    {
        return $this->jenis_kehadiran === 'wfo';
    }

    /**
     * Scope: Get unviewed notulen (for admin badge count).
     */
    public function scopeUnviewed($query)
    {
        return $query->where('viewed_by_admin', false);
    }

    /**
     * Mark notulen as viewed by admin.
     */
    public function markAsViewedByAdmin(): void
    {
        if (!$this->viewed_by_admin) {
            $this->update([
                'viewed_by_admin' => true,
                'admin_viewed_at' => now(),
            ]);
        }
    }

    /**
     * Check if admin has viewed this notulen.
     */
    public function isViewedByAdmin(): bool
    {
        return $this->viewed_by_admin;
    }
}
