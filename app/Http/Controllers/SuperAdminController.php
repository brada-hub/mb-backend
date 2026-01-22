<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Banda;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
     * Listar todas las bandas del sistema (sin filtros de tenant)
     */
    public function listBandas()
    {
        // Obtener todas las bandas directamente (Banda no tiene BelongsToBanda trait)
        $bandas = Banda::orderBy('created_at', 'desc')->get();

        // Agregar conteos manualmente sin global scopes
        $bandas->each(function($banda) {
            $banda->miembros_count = \App\Models\Miembro::withoutGlobalScopes()
                ->where('id_banda', $banda->id_banda)->count();
            $banda->eventos_count = \App\Models\Evento::withoutGlobalScopes()
                ->where('id_banda', $banda->id_banda)->count();
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
            'plan' => 'nullable|string|in:BASIC,PREMIUM,PRO',
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
                'max_miembros' => $request->max_miembros ?? ($request->plan == 'PREMIUM' ? 100 : 15),
                'cuota_mensual' => $request->cuota_mensual ?? 0,
                'fecha_vencimiento' => now()->addMonth(), // Un mes de prueba por defecto
                'notificaciones_habilitadas' => $request->plan != 'BASIC'
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
            'estado' => 'boolean',
            'logo' => 'nullable|image|max:2048'
        ]);

        $data = $request->only(['nombre', 'color_primario', 'color_secundario', 'estado']);

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
        $totalIngresosPrevisibles = Banda::withoutGlobalScopes()
                                        ->where('estado', true)
                                        ->sum('cuota_mensual');

        return response()->json([
            'total_bandas' => Banda::withoutGlobalScopes()->count(),
            'bandas_activas' => Banda::withoutGlobalScopes()->where('estado', true)->count(),
            'total_usuarios' => User::withoutGlobalScopes()->count(),
            'total_miembros' => \App\Models\Miembro::withoutGlobalScopes()->count(),
            'total_eventos' => \App\Models\Evento::withoutGlobalScopes()->count(),
            'ingresos_proyectados' => $totalIngresosPrevisibles,
            'metricas_crecimiento' => [
                'nuevas_bandas_mes' => Banda::withoutGlobalScopes()->whereMonth('created_at', now()->month)->count(),
                'nuevos_musicos_mes' => \App\Models\Miembro::withoutGlobalScopes()->whereMonth('created_at', now()->month)->count(),
                'nuevos_eventos_mes' => \App\Models\Evento::withoutGlobalScopes()->whereMonth('created_at', now()->month)->count()
            ],
            'salud_suscripciones' => [
                'BASIC' => Banda::withoutGlobalScopes()->where('plan', 'BASIC')->count(),
                'PREMIUM' => Banda::withoutGlobalScopes()->where('plan', 'PREMIUM')->count(),
                'PRO' => Banda::withoutGlobalScopes()->where('plan', 'PRO')->count(),
                'por_vencer' => Banda::withoutGlobalScopes()->where('fecha_vencimiento', '<=', now()->addDays(7))->count(),
                'limite_alcanzado' => Banda::withoutGlobalScopes()->whereRaw('id_banda IN (SELECT id_banda FROM miembros GROUP BY id_banda HAVING COUNT(*) >= bandas.max_miembros)')->count()
            ]
        ]);
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
        if (!$user->original_banda_id) {
            $user->original_banda_id = $user->id_banda ?? -1; // -1 indica que venía de "sin banda" (SuperAdmin puro)
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
        if ($user->original_banda_id) {
            $user->id_banda = $user->original_banda_id === -1 ? null : $user->original_banda_id;
            $user->original_banda_id = null;
            $user->save();
        }

        return response()->json([
            'message' => 'Volviste al modo Administrador Monster',
            'user' => $user->load('banda')
        ]);
    }
}
