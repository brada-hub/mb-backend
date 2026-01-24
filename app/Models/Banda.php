<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banda extends Model
{
    use HasFactory, Auditable;

    protected $table = 'bandas';
    protected $primaryKey = 'id_banda';

    protected $fillable = [
        'nombre',
        'slug',
        'logo',
        'color_primario',
        'color_secundario',
        'estado',
        'plan',
        'id_plan',
        'fecha_vencimiento',
        'max_miembros',
        'notificaciones_habilitadas',
        'cuota_mensual'
    ];

    public function subscriptionPlan()
    {
        return $this->belongsTo(Plan::class, 'id_plan');
    }

    public function getCapabilitiesAttribute()
    {
        return $this->subscriptionPlan;
    }

    public function canUseGps()
    {
        return $this->subscriptionPlan?->gps_attendance ?? false;
    }

    public function canUploadAudio()
    {
        return $this->subscriptionPlan?->can_upload_audio ?? false;
    }

    public function canUploadVideo()
    {
        return $this->subscriptionPlan?->can_upload_video ?? false;
    }

    protected $casts = [
        'estado' => 'boolean',
        'notificaciones_habilitadas' => 'boolean',
        'fecha_vencimiento' => 'date',
        'cuota_mensual' => 'float',
        'max_miembros' => 'integer',
        'id_plan' => 'integer'
    ];

    public function miembros()
    {
        return $this->hasMany(Miembro::class, 'id_banda');
    }

    public function eventos()
    {
        return $this->hasMany(Evento::class, 'id_banda');
    }

    public function roles()
    {
        return $this->hasMany(Rol::class, 'id_banda');
    }

    /**
     * Verifica si la banda tiene su suscripción activa
     */
    public function isActiva(): bool
    {
        return $this->estado && ($this->fecha_vencimiento === null || $this->fecha_vencimiento->isFuture());
    }

    /**
     * Verifica si se ha alcanzado el límite de miembros según el plan
     */
    public function haAlcanzadoLimiteMiembros(): bool
    {
        return $this->miembros()->count() >= $this->max_miembros;
    }

    /**
     * Calcula el uso actual de almacenamiento en MB
     */
    public function getCurrentStorageMb(): float
    {
        $totalBytes = 0;

        // Archivos de recursos (scores, audios)
        $archivos = Archivo::whereHas('recurso.tema', function($q) {
            $q->where('id_banda', $this->id_banda);
        })->get();

        foreach ($archivos as $archivo) {
            $path = str_replace('/storage/', '', $archivo->url_archivo);
            if (Storage::disk('public')->exists($path)) {
                $totalBytes += Storage::disk('public')->size($path);
            }
        }

        // Logo de la banda
        if ($this->logo && Storage::disk('public')->exists($this->logo)) {
            $totalBytes += Storage::disk('public')->size($this->logo);
        }

        return round($totalBytes / 1024 / 1024, 2);
    }

    /**
     * Verifica si la banda tiene capacidad para subir un archivo de X bytes
     */
    public function hasStorageCapacity(int $newBytes = 0): bool
    {
        $limitMb = $this->subscriptionPlan->storage_mb ?? 100;
        $currentMb = $this->getCurrentStorageMb();
        $newMb = $newBytes / 1024 / 1024;

        return ($currentMb + $newMb) <= $limitMb;
    }
}
