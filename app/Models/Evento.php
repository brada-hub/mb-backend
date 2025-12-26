<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Evento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'eventos';

    protected $fillable = [
        'nombre',
        'tipo',
        'descripcion',
        'fecha',
        'hora_citacion',
        'hora_inicio',
        'hora_fin',
        'lugar',
        'direccion',
        'latitud',
        'longitud',
        'radio_geofence',
        'tolerancia_minutos',
        'descuento_por_minuto',
        'monto_total',
        'cliente',
        'cliente_celular',
        'estado',
        'lista_confirmada',
        'fecha_confirmacion_lista',
        'creado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_citacion' => 'datetime:H:i',
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'descuento_por_minuto' => 'decimal:2',
        'monto_total' => 'decimal:2',
        'lista_confirmada' => 'boolean',
        'fecha_confirmacion_lista' => 'datetime',
    ];

    // ═══════════════════════════════════════════════════════════
    // CONSTANTES
    // ═══════════════════════════════════════════════════════════

    const TIPO_ENSAYO = 'ensayo';
    const TIPO_CONTRATO = 'contrato';

    const ESTADO_BORRADOR = 'borrador';
    const ESTADO_CONFIRMADO = 'confirmado';
    const ESTADO_EN_CURSO = 'en_curso';
    const ESTADO_FINALIZADO = 'finalizado';
    const ESTADO_CANCELADO = 'cancelado';

    // ═══════════════════════════════════════════════════════════
    // RELACIONES
    // ═══════════════════════════════════════════════════════════

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Miembro::class, 'creado_por');
    }

    public function cupos(): HasMany
    {
        return $this->hasMany(EventoCupo::class);
    }

    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(Miembro::class, 'evento_miembros')
            ->withPivot(['seccion_id', 'estado', 'propuesto_por', 'confirmado_por', 'notificado', 'fecha_notificacion'])
            ->withTimestamps();
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class);
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getFechaFormateadaAttribute(): string
    {
        return $this->fecha->format('d/m/Y');
    }

    public function getHoraCitacionFormateadaAttribute(): string
    {
        return $this->hora_citacion->format('H:i');
    }

    public function getEsContratoAttribute(): bool
    {
        return $this->tipo === self::TIPO_CONTRATO;
    }

    public function getEsEnsayoAttribute(): bool
    {
        return $this->tipo === self::TIPO_ENSAYO;
    }

    public function getTotalMiembrosConfirmadosAttribute(): int
    {
        return $this->miembros()->wherePivot('estado', 'confirmado')->count();
    }

    public function getTotalMiembrosPropuestosAttribute(): int
    {
        return $this->miembros()->wherePivot('estado', 'propuesto')->count();
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTODOS DE GEOLOCALIZACIÓN
    // ═══════════════════════════════════════════════════════════

    /**
     * Calcula la distancia en metros entre el evento y unas coordenadas
     */
    public function calcularDistancia(float $lat, float $lng): float
    {
        $earthRadius = 6371000; // Radio de la Tierra en metros

        $latFrom = deg2rad($this->latitud);
        $lonFrom = deg2rad($this->longitud);
        $latTo = deg2rad($lat);
        $lonTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($latFrom) * cos($latTo) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Verifica si unas coordenadas están dentro del radio del evento
     */
    public function estaDentroDelRadio(float $lat, float $lng): bool
    {
        return $this->calcularDistancia($lat, $lng) <= $this->radio_geofence;
    }

    /**
     * Calcula los minutos de retraso basado en la hora de llegada
     */
    public function calcularMinutosRetraso(\DateTime $horaLlegada): int
    {
        $horaCitacion = $this->fecha->setTimeFrom($this->hora_citacion);
        $horaLimite = $horaCitacion->copy()->addMinutes($this->tolerancia_minutos);

        if ($horaLlegada <= $horaLimite) {
            return 0;
        }

        return $horaLlegada->diffInMinutes($horaLimite);
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTODOS DE LISTA
    // ═══════════════════════════════════════════════════════════

    public function confirmarLista(): void
    {
        // Cambiar todos los propuestos a confirmados
        $this->miembros()
            ->wherePivot('estado', 'propuesto')
            ->update(['evento_miembros.estado' => 'confirmado']);

        $this->update([
            'lista_confirmada' => true,
            'fecha_confirmacion_lista' => now(),
            'estado' => self::ESTADO_CONFIRMADO,
        ]);
    }

    public function getCuposPorSeccion(): array
    {
        return $this->cupos()
            ->with('seccion')
            ->get()
            ->mapWithKeys(fn($cupo) => [$cupo->seccion_id => $cupo->cantidad])
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeEnsayos($query)
    {
        return $query->where('tipo', self::TIPO_ENSAYO);
    }

    public function scopeContratos($query)
    {
        return $query->where('tipo', self::TIPO_CONTRATO);
    }

    public function scopeProximos($query)
    {
        return $query->where('fecha', '>=', now()->toDateString())
            ->orderBy('fecha')
            ->orderBy('hora_citacion');
    }

    public function scopePasados($query)
    {
        return $query->where('fecha', '<', now()->toDateString())
            ->orderByDesc('fecha');
    }

    public function scopeDelMes($query, int $mes, int $año)
    {
        return $query->whereMonth('fecha', $mes)->whereYear('fecha', $año);
    }

    public function scopeActivos($query)
    {
        return $query->whereNotIn('estado', [self::ESTADO_CANCELADO]);
    }
}
