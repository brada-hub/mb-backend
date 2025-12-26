<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    use HasFactory;

    protected $table = 'asistencias';

    protected $fillable = [
        'evento_id',
        'miembro_id',
        'hora_llegada',
        'latitud_llegada',
        'longitud_llegada',
        'estado',
        'minutos_retraso',
        'descuento',
        'registro_manual',
        'registrado_por',
        'observaciones',
        'justificacion',
        'justificado_por',
    ];

    protected $casts = [
        'hora_llegada' => 'datetime',
        'latitud_llegada' => 'decimal:8',
        'longitud_llegada' => 'decimal:8',
        'descuento' => 'decimal:2',
        'registro_manual' => 'boolean',
    ];

    // ═══════════════════════════════════════════════════════════
    // CONSTANTES
    // ═══════════════════════════════════════════════════════════

    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_A_TIEMPO = 'a_tiempo';
    const ESTADO_TARDE = 'tarde';
    const ESTADO_AUSENTE = 'ausente';
    const ESTADO_JUSTIFICADO = 'justificado';

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Miembro::class, 'registrado_por');
    }

    public function justificador(): BelongsTo
    {
        return $this->belongsTo(Miembro::class, 'justificado_por');
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTODOS
    // ═══════════════════════════════════════════════════════════

    /**
     * Registra la llegada del miembro
     */
    public function registrarLlegada(
        float $latitud,
        float $longitud,
        bool $manual = false,
        ?int $registradoPor = null
    ): void {
        $evento = $this->evento;
        $horaLlegada = now();

        // Verificar si está dentro del radio
        $dentroDelRadio = $evento->estaDentroDelRadio($latitud, $longitud);

        if (!$dentroDelRadio && !$manual) {
            throw new \Exception('No estás dentro del área del evento');
        }

        // Calcular retraso
        $minutosRetraso = $evento->calcularMinutosRetraso($horaLlegada);
        $estado = $minutosRetraso > 0 ? self::ESTADO_TARDE : self::ESTADO_A_TIEMPO;

        // Calcular descuento
        $descuento = $minutosRetraso * $evento->descuento_por_minuto;

        $this->update([
            'hora_llegada' => $horaLlegada,
            'latitud_llegada' => $latitud,
            'longitud_llegada' => $longitud,
            'estado' => $estado,
            'minutos_retraso' => $minutosRetraso,
            'descuento' => $descuento,
            'registro_manual' => $manual,
            'registrado_por' => $registradoPor,
        ]);
    }

    /**
     * Justifica la falta
     */
    public function justificar(string $justificacion, int $justificadoPor): void
    {
        $this->update([
            'estado' => self::ESTADO_JUSTIFICADO,
            'justificacion' => $justificacion,
            'justificado_por' => $justificadoPor,
            'descuento' => 0,
        ]);
    }

    /**
     * Marca como ausente
     */
    public function marcarAusente(?string $observaciones = null): void
    {
        $this->update([
            'estado' => self::ESTADO_AUSENTE,
            'observaciones' => $observaciones,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getEstadoTextoAttribute(): string
    {
        return match($this->estado) {
            self::ESTADO_PENDIENTE => 'Pendiente',
            self::ESTADO_A_TIEMPO => 'A tiempo',
            self::ESTADO_TARDE => 'Llegó tarde',
            self::ESTADO_AUSENTE => 'Ausente',
            self::ESTADO_JUSTIFICADO => 'Justificado',
            default => 'Desconocido',
        };
    }

    public function getEstadoColorAttribute(): string
    {
        return match($this->estado) {
            self::ESTADO_PENDIENTE => 'warning',
            self::ESTADO_A_TIEMPO => 'positive',
            self::ESTADO_TARDE => 'orange',
            self::ESTADO_AUSENTE => 'negative',
            self::ESTADO_JUSTIFICADO => 'info',
            default => 'grey',
        };
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeATiempo($query)
    {
        return $query->where('estado', self::ESTADO_A_TIEMPO);
    }

    public function scopeTarde($query)
    {
        return $query->where('estado', self::ESTADO_TARDE);
    }

    public function scopeAusentes($query)
    {
        return $query->where('estado', self::ESTADO_AUSENTE);
    }

    public function scopeConRetraso($query)
    {
        return $query->where('minutos_retraso', '>', 0);
    }
}
