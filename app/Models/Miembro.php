<?php

namespace App\Models;

use App\Traits\BelongsToBanda;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Miembro extends Model
{
    use HasFactory, BelongsToBanda;
    protected $table = 'miembros';
    protected $primaryKey = 'id_miembro';
    protected $fillable = [
        'id_categoria',
        'nombres',
        'apellidos',
        'ci',
        'celular',
        'fecha',
        'latitud',
        'longitud',
        'direccion',
        'id_seccion',
        'id_instrumento',
        'id_voz',
        'id_rol',
        'version_perfil',
        'id_banda'
    ];

    protected $casts = [
        'fecha' => 'datetime:Y/m/d',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class, 'id_miembro');
    }

    public function categoria(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }

    public function seccion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Seccion::class, 'id_seccion');
    }

    public function instrumento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Instrumento::class, 'id_instrumento');
    }

    public function voz(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VozInstrumental::class, 'id_voz');
    }

    public function rol(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol');
    }

    public function contactos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ContactoReferencia::class, 'id_miembro');
    }

    public function convocatorias(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ConvocatoriaEvento::class, 'id_miembro');
    }

    public function asistencias(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Asistencia::class, ConvocatoriaEvento::class, 'id_miembro', 'id_convocatoria', 'id_miembro', 'id_convocatoria');
    }

    public function permisos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'miembro_permiso', 'id_miembro', 'id_permiso')
                    ->withPivot('estado_booleano')
                    ->withTimestamps();
    }

    public function calculateStreak(): int
    {
        $records = \Illuminate\Support\Facades\DB::table('convocatoria_evento')
            ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
            ->leftJoin('asistencias', 'convocatoria_evento.id_convocatoria', '=', 'asistencias.id_convocatoria')
            ->where('convocatoria_evento.id_miembro', $this->id_miembro)
            ->where('eventos.fecha', '<', \Carbon\Carbon::today()->toDateString())
            ->where('eventos.estado', true)
            ->select('asistencias.estado', 'eventos.fecha')
            ->orderBy('eventos.fecha', 'desc')
            ->get();

        $streak = 0;
        foreach ($records as $rec) {
            if (in_array($rec->estado, ['PUNTUAL', 'RETRASO'])) {
                $streak++;
            } else if ($rec->estado === 'FALTA' || is_null($rec->estado)) {
                break;
            }
        }
        return $streak;
    }
}
