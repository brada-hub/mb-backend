<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'notificaciones';

    protected $fillable = [
        'miembro_id',
        'tipo',
        'titulo',
        'mensaje',
        'data',
        'leida',
        'leida_at',
        'enviada_whatsapp',
        'enviada_whatsapp_at',
    ];

    protected $casts = [
        'data' => 'array',
        'leida' => 'boolean',
        'leida_at' => 'datetime',
        'enviada_whatsapp' => 'boolean',
        'enviada_whatsapp_at' => 'datetime',
    ];

    // ═══════════════════════════════════════════════════════════
    // CONSTANTES DE TIPOS
    // ═══════════════════════════════════════════════════════════

    const TIPO_EVENTO = 'evento';
    const TIPO_PAGO = 'pago';
    const TIPO_ASISTENCIA = 'asistencia';
    const TIPO_SISTEMA = 'sistema';
    const TIPO_PARTITURA = 'partitura';

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTODOS
    // ═══════════════════════════════════════════════════════════

    public function marcarComoLeida(): void
    {
        $this->update([
            'leida' => true,
            'leida_at' => now(),
        ]);
    }

    /**
     * Crea una notificación para un miembro
     */
    public static function enviar(
        int $miembroId,
        string $tipo,
        string $titulo,
        string $mensaje,
        array $data = []
    ): self {
        return self::create([
            'miembro_id' => $miembroId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'data' => $data,
        ]);
    }

    /**
     * Envía notificación a múltiples miembros
     */
    public static function enviarMasivo(
        array $miembroIds,
        string $tipo,
        string $titulo,
        string $mensaje,
        array $data = []
    ): void {
        foreach ($miembroIds as $miembroId) {
            self::enviar($miembroId, $tipo, $titulo, $mensaje, $data);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getIconoAttribute(): string
    {
        return match($this->tipo) {
            self::TIPO_EVENTO => 'event',
            self::TIPO_PAGO => 'payments',
            self::TIPO_ASISTENCIA => 'how_to_reg',
            self::TIPO_PARTITURA => 'music_note',
            default => 'notifications',
        };
    }

    public function getColorAttribute(): string
    {
        return match($this->tipo) {
            self::TIPO_EVENTO => 'primary',
            self::TIPO_PAGO => 'positive',
            self::TIPO_ASISTENCIA => 'info',
            self::TIPO_PARTITURA => 'purple',
            default => 'grey',
        };
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeNoLeidas($query)
    {
        return $query->where('leida', false);
    }

    public function scopeDeMiembro($query, int $miembroId)
    {
        return $query->where('miembro_id', $miembroId);
    }

    public function scopeRecientes($query, int $dias = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($dias));
    }
}
