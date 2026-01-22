<?php

namespace App\Traits;

use App\Models\Banda;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToBanda
{
    /**
     * El "boot" del trait se ejecuta automáticamente al iniciar el modelo.
     */
    protected static function bootBelongsToBanda()
    {
        // Al crear un registro, le asignamos automáticamente la banda del usuario autenticado
        static::creating(function ($model) {
            if (empty($model->id_banda) && auth()->check()) {
                $model->id_banda = auth()->user()->id_banda;
            }
        });

        // Aplicamos un Scope Global para que todas las consultas se filtren por banda
        static::addGlobalScope('banda', function (Builder $builder) {
            if (auth()->check()) {
                $user = auth()->user();
                \Illuminate\Support\Facades\Log::info("Scope Debug: User {$user->user} (ID: {$user->id_user}) - Banda: {$user->id_banda} - SuperAdmin: " . ($user->isSuperAdmin() ? 'YES' : 'NO'));

                // IGNORAR SCOPE SI ES SUPER ADMIN (Permitir ver todo)
                // PERO: Si el Super Admin tiene una banda asignada (impersonate),
                // entonces SÍ queremos filtrar por esa banda para que el modo soporte sea realista.
                if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() && empty($user->id_banda)) {
                    return;
                }

                $builder->where(function ($q) use ($builder, $user) {
                    $table = $builder->getQuery()->from;
                    $q->where($table . '.id_banda', '=', $user->id_banda)
                      ->orWhereNull($table . '.id_banda');
                });
            }
        });
    }

    public function banda()
    {
        return $this->belongsTo(Banda::class, 'id_banda');
    }
}
