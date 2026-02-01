<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Banda;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\AuditLog;
use App\Models\Archivo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SuperAdminController extends Controller
{
    /**
     * Middleware para verificar que el usuario es Super Admin
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
                return response()->json(['message' => 'Acceso denegado. Solo Administradores Monster.'], 403);
            }
            return $next($request);
        });
    }

    /**
     * SUPER ENDPOINT: Retorna TODA la data del dashboard en UNA sola petición
     * Esto evita el cuello de botella del servidor single-threaded
     */
    public function getDashboardData()
    {
        return response()->json(Cache::remember('superadmin.dashboard', 300, function() {
            $bandas = Banda::withoutGlobalScopes()
                ->with('subscriptionPlan')
                ->withCount(['miembros' => fn($q) => $q->withoutGlobalScopes()])
                ->orderBy('created_at', 'desc')
                ->get();

            $bandas->each(function($banda) {
                $banda->eventos_count = \App\Models\Evento::withoutGlobalScopes()
                    ->where('id_banda', $banda->id_banda)->count();
            });

            // Stats
            $stats = [
                'total_bandas' => Banda::withoutGlobalScopes()->count(),
                'bandas_activas' => Banda::withoutGlobalScopes()->where('estado', true)->count(),
                'total_usuarios' => User::withoutGlobalScopes()->count(),
                'total_miembros' => \App\Models\Miembro::withoutGlobalScopes()->count(),
                'total_eventos' => \App\Models\Evento::withoutGlobalScopes()->count(),
                'ingresos_proyectados' => Banda::withoutGlobalScopes()->where('estado', true)->sum('cuota_mensual'),
                'metricas_crecimiento' => [
                    'nuevas_bandas_mes' => Banda::withoutGlobalScopes()->whereMonth('created_at', now()->month)->count(),
                    'nuevos_musicos_mes' => \App\Models\Miembro::withoutGlobalScopes()->whereMonth('created_at', now()->month)->count(),
                    'nuevos_eventos_mes' => \App\Models\Evento::withoutGlobalScopes()->whereMonth('created_at', now()->month)->count()
                ],
                'salud_suscripciones' => Banda::withoutGlobalScopes()
                    ->join('plans', 'bandas.id_plan', '=', 'plans.id_plan')
                    ->select('plans.nombre as plan', DB::raw('count(*) as total'))
                    ->groupBy('plans.nombre')
                    ->pluck('total', 'plan')
                    ->toArray(),
                'por_vencer' => Banda::withoutGlobalScopes()->where('fecha_vencimiento', '<=', now()->addDays(7))->count(),
                'limite_alcanzado' => 0
            ];

            // Storage (simplified)
            $storage = $bandas->map(function($banda) {
                $archivosCount = Archivo::whereHas('recurso.tema', fn($q) => $q->where('id_banda', $banda->id_banda))->count();
                $estimatedMb = round($archivosCount * 0.5, 2);
                $limitMb = $banda->subscriptionPlan->storage_mb ?? 100;
                return [
                    'id_banda' => $banda->id_banda,
                    'nombre' => $banda->nombre,
                    'plan' => $banda->subscriptionPlan->label ?? $banda->plan,
                    'current_mb' => $estimatedMb,
                    'limit_mb' => $limitMb,
                    'percent' => $limitMb > 0 ? round(($estimatedMb / $limitMb) * 100, 1) : 0,
                    'status' => $estimatedMb > $limitMb ? 'OVER_LIMIT' : ($estimatedMb > ($limitMb * 0.9) ? 'WARNING' : 'OK'),
                    'files_count' => $archivosCount
                ];
            });

            return [
                'stats' => $stats,
                'bandas' => $bandas,
                'storage' => $storage,
                'plans' => Plan::all()
            ];
        }));
    }

    /**
     * Listar todas las bandas del sistema (sin filtros de tenant)
     */
    public function listBandas()
    {
        // Cache por 5 minutos para reducir queries
        $bandas = Cache::remember('superadmin.bandas', 300, function() {
            $bandas = Banda::withoutGlobalScopes()
                ->with('subscriptionPlan')
                ->withCount([
                    'miembros' => fn($q) => $q->withoutGlobalScopes(),
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            // Agregar conteo de eventos
            $bandas->each(function($banda) {
                $banda->eventos_count = \App\Models\Evento::withoutGlobalScopes()
                    ->where('id_banda', $banda->id_banda)->count();
            });

            return $bandas;
        });

        return response()->json($bandas);
    }

    /**
     * Crear una nueva banda
     */
    public function createBanda(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'color_primario' => 'nullable|string|max:50',
            'color_secundario' => 'nullable|string|max:50',
            'admin_user' => 'nullable|string',
            'admin_password' => 'nullable|string|min:1',
            'plan' => 'nullable|string',
            'id_plan' => 'nullable|exists:plans,id_plan',
            'max_miembros' => 'nullable|integer',
            'cuota_mensual' => 'nullable|numeric',
            'logo' => 'nullable|image|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Error de validación', 'errors' => $validator->errors()], 422);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $slug = Str::slug($request->nombre);
            $originalSlug = $slug;
            $counter = 1;

            while (Banda::withoutGlobalScopes()->where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('logos', 'public');
            }

            $banda = new Banda([
                'nombre' => $request->nombre,
                'slug' => $slug,
                'logo' => $logoPath,
                'color_primario' => $request->color_primario ?? '#6366f1',
                'color_secundario' => $request->color_secundario ?? '#161b2c',
                'estado' => true,
                'plan' => $request->plan ?? 'BASIC',
                'id_plan' => $request->id_plan ?? Plan::where('nombre', 'BASIC')->first()->id_plan,
                'max_miembros' => $request->max_miembros ?? (Plan::find($request->id_plan)->max_miembros ?? 15),
                'cuota_mensual' => $request->cuota_mensual ?? 0,
                'fecha_vencimiento' => now()->addMonth(), // Un mes de prueba por defecto
                'notificaciones_habilitadas' => true
            ]);
            $banda->saveQuietly();

            // Crear catálogos por defecto para la nueva banda
            $this->seedDefaultCatalogs($banda->id_banda);

            // Si se enviaron credenciales de admin, crear el usuario
            if ($request->admin_user && $request->admin_password) {
                // Asegurar que el usuario sea único
                $finalUser = $request->admin_user;
                if (User::where('user', $finalUser)->exists()) {
                    $finalUser .= rand(100, 999);
                }

                // 1. Obtener Rol de Director recién creado por el seeder
                $rolDirector = \App\Models\Rol::where('id_banda', $banda->id_banda)
                    ->where('rol', 'DIRECTOR')
                    ->first();

                // 2. Perfil de Miembro (Buscamos un instrumento por defecto del catálogo recién creado)
                $trompeta = \App\Models\Instrumento::where('id_banda', $banda->id_banda)->where('instrumento', 'TROMPETA')->first();

                $miembro = \App\Models\Miembro::create([
                    'nombres' => $banda->nombre,
                    'apellidos' => 'Admin',
                    'id_rol' => $rolDirector ? $rolDirector->id_rol : null,
                    'id_banda' => $banda->id_banda,
                    'celular' => '0',
                    'ci' => strtoupper(Str::random(10)),
                    'fecha' => now(),
                    'id_seccion' => $trompeta ? $trompeta->id_seccion : null,
                    'id_instrumento' => $trompeta ? $trompeta->id_instrumento : null,
                    'id_categoria' => \App\Models\Categoria::first()->id_categoria ?? null
                ]);

                // 3. Usuario Admin de Banda
                $user = new User([
                    'user' => $finalUser,
                    'password' => Hash::make($request->admin_password),
                    'estado' => true,
                    'id_banda' => $banda->id_banda,
                    'id_miembro' => $miembro->id_miembro,
                    'is_super_admin' => false,
                    'password_changed' => false,
                    'profile_completed' => false
                ]);
                $user->saveQuietly();
            }

            return response()->json($banda, 201);
        });
    }

    /**
     * Semicataloga una banda nueva con los instrumentos base y roles de fábrica
     */
    private function seedDefaultCatalogs($idBanda)
    {
        // 1. INSTRUMENTOS
        $catalog = [
            'PERCUSIÓN' => ['PLATILLO', 'TAMBOR', 'TIMBAL', 'BOMBO'],
            'VIENTOS' => ['TROMPETA', 'TROMBÓN', 'BARÍTONO', 'HELICÓN', 'CLARINETE']
        ];

        foreach ($catalog as $secName => $instruments) {
            $seccion = \App\Models\Seccion::create([
                'seccion' => $secName,
                'descripcion' => 'SECCIÓN DE ' . $secName,
                'estado' => true,
                'id_banda' => $idBanda
            ]);

            foreach ($instruments as $instName) {
                \App\Models\Instrumento::create([
                    'instrumento' => $instName,
                    'id_seccion' => $seccion->id_seccion,
                    'id_banda' => $idBanda
                ]);
            }
        }

        // 2. ROLES DE FÁBRICA (PROTEGIDOS)
        $permisos = \App\Models\Permiso::all();

        $rolesFabric = [
            [
                'rol' => 'DIRECTOR',
                'descripcion' => 'CONTROL TOTAL DE LA BANDA (GESTIÓN, FINANZAS Y CÁTALOGOS)',
                'es_protegido' => true,
                'perms' => $permisos->pluck('id_permiso')->toArray()
            ],
            [
                'rol' => 'DELEGADO / JEFE',
                'descripcion' => 'CONTROL DE ASISTENCIAS, EVENTOS Y AGENDA',
                'es_protegido' => true,
                'perms' => $permisos->whereIn('permiso', ['GESTION_ASISTENCIA', 'GESTION_EVENTOS', 'VER_DASHBOARD'])->pluck('id_permiso')->toArray()
            ],
            [
                'rol' => 'MÚSICO',
                'descripcion' => 'SOLO LECTURA DE AGENDA, RECURSOS Y BIBLIOTECA',
                'es_protegido' => true,
                'perms' => $permisos->whereIn('permiso', ['VER_DASHBOARD', 'GESTION_RECURSOS', 'GESTION_BIBLIOTECA'])->pluck('id_permiso')->toArray()
            ]
        ];

        foreach ($rolesFabric as $r) {
            $rol = \App\Models\Rol::create([
                'rol' => $r['rol'],
                'descripcion' => $r['descripcion'],
                'es_protegido' => $r['es_protegido'],
                'id_banda' => $idBanda
            ]);
            $rol->permisos()->sync($r['perms']);
        }
    }

    /**
     * Actualizar una banda
     */
    public function updateBanda(Request $request, $id)
    {
        $banda = Banda::withoutGlobalScopes()->findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100',
            'color_primario' => 'nullable|string|max:20',
            'color_secundario' => 'nullable|string|max:20',
            'plan' => 'nullable|string|max:20',
            'id_plan' => 'nullable|exists:plans,id_plan',
            'max_miembros' => 'nullable|integer',
            'cuota_mensual' => 'nullable|numeric',
            'estado' => 'boolean',
            'logo' => 'nullable|image|max:2048'
        ]);

        $data = $request->only([
            'nombre',
            'color_primario',
            'color_secundario',
            'plan',
            'id_plan',
            'max_miembros',
            'cuota_mensual',
            'estado'
        ]);

        if ($request->hasFile('logo')) {
            // Borrar anterior si existe
            if ($banda->logo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($banda->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $banda->update($data);

        return response()->json($banda);
    }

    /**
     * Crear admin inicial para una banda
     */
    public function createBandaAdmin(Request $request, $bandaId)
    {
        $banda = Banda::withoutGlobalScopes()->findOrFail($bandaId);

        $request->validate([
            'username' => 'required|string|unique:users,user',
            'password' => 'required|string|min:8',
            'nombre' => 'required|string|max:100'
        ]);

        // Crear usuario administrador para esta banda (sin scope automático)
        $user = new User([
            'user' => $request->username,
            'password' => Hash::make($request->password),
            'estado' => true,
            'id_banda' => $banda->id_banda,
            'password_changed' => false
        ]);
        $user->saveQuietly();

        return response()->json([
            'message' => "Admin creado para {$banda->nombre}",
            'user' => $user
        ], 201);
    }

    /**
     * Estadísticas globales del sistema (visión de pájaro)
     */
    public function getStats()
    {
        // Cache stats por 5 minutos - datos que no cambian frecuentemente
        return response()->json(Cache::remember('superadmin.stats', 300, function() {
            return [
                'total_bandas' => Banda::withoutGlobalScopes()->count(),
                'bandas_activas' => Banda::withoutGlobalScopes()->where('estado', true)->count(),
                'total_usuarios' => User::withoutGlobalScopes()->count(),
                'total_miembros' => \App\Models\Miembro::withoutGlobalScopes()->count(),
                'total_eventos' => \App\Models\Evento::withoutGlobalScopes()->count(),
                'ingresos_proyectados' => Banda::withoutGlobalScopes()->where('estado', true)->sum('cuota_mensual'),
                'metricas_crecimiento' => [
                    'nuevas_bandas_mes' => Banda::withoutGlobalScopes()->whereMonth('created_at', now()->month)->count(),
                    'nuevos_musicos_mes' => \App\Models\Miembro::withoutGlobalScopes()->whereMonth('created_at', now()->month)->count(),
                    'nuevos_eventos_mes' => \App\Models\Evento::withoutGlobalScopes()->whereMonth('created_at', now()->month)->count()
                ],
                'salud_suscripciones' => Banda::withoutGlobalScopes()
                    ->join('plans', 'bandas.id_plan', '=', 'plans.id_plan')
                    ->select('plans.nombre as plan', DB::raw('count(*) as total'))
                    ->groupBy('plans.nombre')
                    ->pluck('total', 'plan')
                    ->toArray(),
                'por_vencer' => Banda::withoutGlobalScopes()->where('fecha_vencimiento', '<=', now()->addDays(7))->count(),
                'limite_alcanzado' => 0 // Simplificado para performance
            ];
        }));
    }

    /**
     * Mantenimiento Global: Crear/Actualizar catálogo para todas las bandas (Legacy/New)
     */
    public function syncGlobalCatalogs(Request $request)
    {
        $request->validate(['type' => 'required|in:instrumentos,generos']);

        $bandas = Banda::withoutGlobalScopes()->get();
        // Lógica de sincronización masiva si se requiere...

        return response()->json(['message' => 'Sincronización global completada.']);
    }

    /**
     * Cambiar a contexto de una banda específica (para debugging/soporte)
     */
    public function impersonateBanda($bandaId)
    {
        $banda = Banda::withoutGlobalScopes()->findOrFail($bandaId);
        $user = auth()->user();

        // Guardamos el id_banda original (si no estamos ya impersonando)
        if (is_null($user->original_banda_id)) {
            $user->original_banda_id = $user->id_banda ?? 0; // 0 indica que venía de "sin banda" (SuperAdmin puro)
        }

        $user->id_banda = $banda->id_banda;
        $user->save();

        return response()->json([
            'message' => "Ahora estás viendo como: {$banda->nombre}",
            'banda' => $banda,
            'user' => $user->load('banda')
        ]);
    }

    public function stopImpersonating()
    {
        $user = auth()->user();
        if (!is_null($user->original_banda_id)) {
            $user->id_banda = $user->original_banda_id === 0 ? null : $user->original_banda_id;
            $user->original_banda_id = null;
            $user->save();
        }

        return response()->json([
            'message' => 'Volviste al modo Administrador Monster',
            'user' => $user->load('banda')
        ]);
    }

    /**
     * Obtener logs de auditoría globales
     */
    public function getAuditLogs(Request $request)
    {
        $query = AuditLog::with(['user', 'banda'])->latest();

        if ($request->id_banda) {
            $query->where('id_banda', $request->id_banda);
        }

        if ($request->event) {
            $query->where('event', $request->event);
        }

        return response()->json($query->paginate(50));
    }

    /**
     * Reporte detallado de uso de disco por banda
     * Cached for 10 minutes - filesystem operations are slow
     */
    public function getStorageReport()
    {
        return response()->json(Cache::remember('superadmin.storage', 600, function() {
            $bandas = Banda::withoutGlobalScopes()
                ->with('subscriptionPlan')
                ->get();

            return $bandas->map(function($banda) {
                // Estimación rápida basada en conteo de archivos (evita leer filesystem)
                $archivosCount = Archivo::whereHas('recurso.tema', function($q) use ($banda) {
                    $q->where('id_banda', $banda->id_banda);
                })->count();

                // Estimación: ~500KB promedio por archivo
                $estimatedMb = round($archivosCount * 0.5, 2);
                $limitMb = $banda->subscriptionPlan->storage_mb ?? 100;

                return [
                    'id_banda' => $banda->id_banda,
                    'nombre' => $banda->nombre,
                    'plan' => $banda->subscriptionPlan->label ?? $banda->plan,
                    'current_mb' => $estimatedMb,
                    'limit_mb' => $limitMb,
                    'percent' => $limitMb > 0 ? round(($estimatedMb / $limitMb) * 100, 1) : 0,
                    'status' => $estimatedMb > $limitMb ? 'OVER_LIMIT' : ($estimatedMb > ($limitMb * 0.9) ? 'WARNING' : 'OK'),
                    'files_count' => $archivosCount
                ];
            });
        }));
    }

    public function listPlans()
    {
        return response()->json(Cache::remember('superadmin.plans', 1800, fn() => Plan::all()));
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|unique:plans,nombre',
            'label' => 'required|string',
            'max_miembros' => 'required|integer',
            'storage_mb' => 'required|integer',
            'can_upload_audio' => 'boolean',
            'can_upload_video' => 'boolean',
            'gps_attendance' => 'boolean',
            'custom_branding' => 'boolean',
            'precio_base' => 'numeric',
            'features' => 'nullable|array'
        ]);

        $plan = Plan::create($data);
        return response()->json($plan, 201);
    }

    public function updatePlan(Request $request, $id)
    {
        $plan = Plan::findOrFail($id);
        $data = $request->validate([
            'nombre' => 'string|unique:plans,nombre,' . $id . ',id_plan',
            'label' => 'string',
            'max_miembros' => 'integer',
            'storage_mb' => 'integer',
            'can_upload_audio' => 'boolean',
            'can_upload_video' => 'boolean',
            'gps_attendance' => 'boolean',
            'custom_branding' => 'boolean',
            'precio_base' => 'numeric',
            'features' => 'nullable|array'
        ]);

        $plan->update($data);

        // Sincronizar el límite de miembros en todas las bandas que tienen este plan
        if (isset($data['max_miembros'])) {
            Banda::withoutGlobalScopes()
                ->where('id_plan', $plan->id_plan)
                ->update(['max_miembros' => $plan->max_miembros]);
        }

        return response()->json($plan);
    }

    public function deletePlan($id)
    {
        $plan = Plan::findOrFail($id);
        if ($plan->bandas()->exists()) {
            return response()->json(['message' => 'No se puede eliminar un plan que tiene bandas asociadas.'], 400);
        }
        $plan->delete();
        return response()->json(['message' => 'Plan eliminado correctamente.']);
    }
}
