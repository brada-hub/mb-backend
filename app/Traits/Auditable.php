<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function ($model) {
            self::logAction('created', $model);
        });

        static::updated(function ($model) {
            self::logAction('updated', $model);
        });

        static::deleted(function ($model) {
            self::logAction('deleted', $model);
        });
    }

    protected static function logAction($event, $model)
    {
        // Don't log AuditLog itself to avoid recursion
        if ($model instanceof AuditLog) return;

        $user = Auth::user();
        $idBanda = $user->id_banda ?? null;

        // Si el usuario es admin global o no tiene banda, intentamos sacarla del modelo
        if (!$idBanda) {
            if (isset($model->id_banda)) {
                $idBanda = $model->id_banda;
            } elseif (method_exists($model, 'recurso') && $model->recurso?->tema?->id_banda) {
                $idBanda = $model->recurso->tema->id_banda;
            } elseif (method_exists($model, 'tema') && $model->tema?->id_banda) {
                $idBanda = $model->tema->id_banda;
            }
        }

        AuditLog::create([
            'id_user' => $user->id_user ?? null,
            'id_banda' => $idBanda,
            'event' => $event,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $event === 'updated' ? array_intersect_key($model->getOriginal(), $model->getDirty()) : null,
            'new_values' => $event === 'deleted' ? null : $model->getAttributes(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'message' => "Entidad ".class_basename($model)." fue {$event}."
        ]);
    }
}
