<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Partitura extends Model
{
    use HasFactory;

    protected $table = 'partituras';

    protected $fillable = [
        'tema_id',
        'seccion_id',
        'titulo',
        'tipo_archivo',
        'archivo',
        'archivo_original',
        'tamaño_bytes',
        'notas',
        'subido_por',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // ═══════════════════════════════════════════════════════════
    // CONSTANTES
    // ═══════════════════════════════════════════════════════════

    const TIPO_PDF = 'pdf';
    const TIPO_IMAGEN = 'imagen';
    const TIPO_AUDIO = 'audio';

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function tema(): BelongsTo
    {
        return $this->belongsTo(Tema::class);
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function subidor(): BelongsTo
    {
        return $this->belongsTo(Miembro::class, 'subido_por');
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getUrlAttribute(): string
    {
        return Storage::url($this->archivo);
    }

    public function getTamañoFormateadoAttribute(): string
    {
        $bytes = $this->tamaño_bytes;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    public function getIconoTipoAttribute(): string
    {
        return match($this->tipo_archivo) {
            self::TIPO_PDF => 'picture_as_pdf',
            self::TIPO_IMAGEN => 'image',
            self::TIPO_AUDIO => 'audiotrack',
            default => 'insert_drive_file',
        };
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopePdfs($query)
    {
        return $query->where('tipo_archivo', self::TIPO_PDF);
    }

    public function scopeImagenes($query)
    {
        return $query->where('tipo_archivo', self::TIPO_IMAGEN);
    }

    public function scopeAudios($query)
    {
        return $query->where('tipo_archivo', self::TIPO_AUDIO);
    }

    public function scopeDeSeccion($query, int $seccionId)
    {
        return $query->where('seccion_id', $seccionId);
    }
}
