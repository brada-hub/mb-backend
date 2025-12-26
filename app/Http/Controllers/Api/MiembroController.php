<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Miembro;
use App\Models\User;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MiembroController extends Controller
{
    /**
     * ═══════════════════════════════════════════════════════════
     * LISTAR MIEMBROS (Optimizado para rendimiento)
     * ═══════════════════════════════════════════════════════════
     */
    public function index(Request $request): JsonResponse
    {
        // Eager loading optimizado: seleccionamos solo los campos necesarios
        $query = Miembro::with([
            'seccion:id,nombre,nombre_corto,color,icono',
            'categoria:id,codigo,nombre',
            'user' => function($q) {
                $q->select(['id', 'username', 'activo', 'multi_login', 'device_id'])
                  ->with('roles:id,nombre,slug,nivel');
            }
        ])
        ->select([
            'id', 'nombres', 'apellidos', 'ci_numero', 'ci_complemento',
            'celular', 'foto', 'seccion_id', 'categoria_id', 'user_id',
            'fecha_nacimiento', 'direccion', 'latitud', 'longitud',
            'referencia_nombre', 'referencia_celular'
        ])
        ->whereHas('user', function($q) {
            $q->where('activo', true);
        });

        // Filtros optimizados
        if ($request->filled('seccion_id')) {
            $query->where('seccion_id', $request->seccion_id);
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->filled('rol_id')) {
            $rolId = $request->rol_id;
            $query->whereHas('user.roles', function($q) use ($rolId) {
                $q->where('roles.id', $rolId);
            });
        }

        // Búsqueda optimizada con índices
        if ($request->filled('buscar')) {
            $buscar = trim($request->buscar);
            // Usar búsqueda más eficiente con índices
            $query->where(function ($q) use ($buscar) {
                $q->whereRaw("nombres ILIKE ?", ["%{$buscar}%"])
                    ->orWhereRaw("apellidos ILIKE ?", ["%{$buscar}%"])
                    ->orWhereRaw("ci_numero ILIKE ?", ["%{$buscar}%"]);
            });
        }

        $miembros = $query->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate($request->get('per_page', 20));

        // Transformar data para mantener compatibilidad con frontend (rol flatten)
        $miembros->getCollection()->transform(function ($miembro) {
            return $this->transformMiembro($miembro);
        });

        return response()->json([
            'success' => true,
            'data' => $miembros,
        ]);
    }


    /**
     * ═══════════════════════════════════════════════════════════
     * VER MIEMBRO
     * ═══════════════════════════════════════════════════════════
     */
    public function show(Miembro $miembro): JsonResponse
    {
        $miembro->load(['seccion', 'categoria', 'user.roles']);

        return response()->json([
            'success' => true,
            'data' => $this->transformMiembro($miembro),
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * CREAR MIEMBRO
     * ═══════════════════════════════════════════════════════════
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombres' => 'required|string|max:30',
            'apellidos' => 'required|string|max:30',
            'ci' => 'required|string|max:12', // Max 12 chars
            'celular' => ['required', 'regex:/^[67]\d{7}$/'],
            'fecha_nacimiento' => 'required|date|before:today',
            'direccion' => 'nullable|string',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'referencia_nombre' => ['nullable', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'referencia_celular' => ['nullable', 'regex:/^[67]\d{7}$/'],
            'seccion_id' => 'required|exists:secciones,id',
            'categoria_id' => 'required|exists:categorias_salariales,id',
            'rol_id' => 'required|exists:roles,id',
        ]);

        return DB::transaction(function () use ($request) {
            // ... (resto igual) ...
            $usuario = $this->generarUsuario($request->nombres, $request->apellidos);
            $password = $this->generarPassword();

            // Separar CI
            $ciParts = explode('-', $request->ci);
            $ciNumero = $ciParts[0];
            $ciComplemento = isset($ciParts[1]) ? substr($ciParts[1], 0, 5) : null;

            // Validar unicidad manual
            $existe = Miembro::where('ci_numero', $ciNumero)
                ->where('ci_complemento', $ciComplemento)
                ->exists();

            if ($existe) {
                throw ValidationException::withMessages(['ci' => ['El CI ya está registrado.']]);
            }

            // 2. Crear Usuario
            $user = User::create([
                'username' => $usuario,
                'password' => Hash::make($password),
                'activo' => true,
                'multi_login' => false,
                'cambio_password_requerido' => true,
            ]);

            // 3. Asignar Rol
            $user->roles()->attach($request->rol_id);

            // 4. Crear Miembro
            $miembro = Miembro::create([
                'user_id' => $user->id,
                'nombres' => $request->nombres,
                'apellidos' => $request->apellidos,
                'ci_numero' => $ciNumero,
                'ci_complemento' => $ciComplemento,
                'celular' => (int) $request->celular,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'direccion' => $request->direccion,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'referencia_nombre' => $request->referencia_nombre,
                'referencia_celular' => $request->referencia_celular,
                'seccion_id' => $request->seccion_id,
                'categoria_id' => $request->categoria_id,
                'notas' => $request->notas,
            ]);

            $miembro->load(['seccion', 'categoria', 'user.roles']);

            return response()->json([
                'success' => true,
                'message' => 'Miembro registrado correctamente',
                'data' => [
                    'miembro' => $this->transformMiembro($miembro),
                    'credenciales' => [
                        'usuario' => $usuario,
                        'password' => $password,
                    ],
                ],
            ], 201);
        });
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * ACTUALIZAR MIEMBRO
     * ═══════════════════════════════════════════════════════════
     */
    public function update(Request $request, Miembro $miembro): JsonResponse
    {
        $request->validate([
            'nombres' => 'sometimes|string|max:30',
            'apellidos' => 'sometimes|string|max:30',
            'ci' => 'sometimes|string|max:12',
            'celular' => ['sometimes', 'regex:/^[67]\d{7}$/'],
            'fecha_nacimiento' => 'sometimes|nullable|date|before:today',
            'referencia_nombre' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'referencia_celular' => ['sometimes', 'nullable', 'regex:/^[67]\d{7}$/'],
            'seccion_id' => 'sometimes|exists:secciones,id',
            'categoria_id' => 'sometimes|exists:categorias_salariales,id',
            'rol_id' => 'sometimes|exists:roles,id',
            'activo' => 'sometimes|boolean',
        ]);

        return DB::transaction(function () use ($request, $miembro) {
            // Actualizar datos de Miembro
            $data = $request->except(['rol_id', 'activo', 'ci']);

            if ($request->has('ci')) {
                $ciParts = explode('-', $request->ci);
                $ciNumero = $ciParts[0];
                $ciComplemento = isset($ciParts[1]) ? substr($ciParts[1], 0, 5) : null;

                // Validar unicidad (excluyendo actual)
                $existe = Miembro::where('ci_numero', $ciNumero)
                    ->where('ci_complemento', $ciComplemento)
                    ->where('id', '!=', $miembro->id)
                    ->exists();

                if ($existe) {
                    throw ValidationException::withMessages(['ci' => ['El CI ya está registrado.']]);
                }

                $data['ci_numero'] = $ciNumero;
                $data['ci_complemento'] = $ciComplemento;
            }

            $miembro->update($data);

            // Actualizar datos de Usuario (Rol y Activo)
            if ($miembro->user) {
                if ($request->has('rol_id')) {
                    $miembro->user->roles()->sync([$request->rol_id]);
                }
                if ($request->has('activo')) {
                    $miembro->user->update(['activo' => $request->activo]);
                }
            }

            $miembro->load(['seccion', 'categoria', 'user.roles']);

            return response()->json([
                'success' => true,
                'message' => 'Miembro actualizado correctamente',
                'data' => $this->transformMiembro($miembro),
            ]);
        });
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * ELIMINAR MIEMBRO (Soft Delete)
     * ═══════════════════════════════════════════════════════════
     */
    public function destroy(Miembro $miembro): JsonResponse
    {
        if ($miembro->user) {
            $miembro->user->update(['activo' => false]);
            // Opcional: $miembro->user->delete(); si queremos soft delete del user también
        }
        $miembro->delete();

        return response()->json([
            'success' => true,
            'message' => 'Miembro eliminado correctamente',
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * RESTABLECER CONTRASEÑA
     * ═══════════════════════════════════════════════════════════
     */
    public function restablecerPassword(Miembro $miembro): JsonResponse
    {
        if (!$miembro->user) {
             return response()->json(['success' => false, 'message' => 'Miembro sin usuario asociado'], 400);
        }

        $password = $this->generarPassword();

        $miembro->user->update([
            'password' => Hash::make($password),
            'cambio_password_requerido' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contraseña restablecida correctamente',
            'data' => [
                'usuario' => $miembro->user->username,
                'password' => $password,
            ],
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * CAMBIAR DISPOSITIVO
     * ═══════════════════════════════════════════════════════════
     */
    public function cambiarDispositivo(Request $request, Miembro $miembro): JsonResponse
    {
        $request->validate([
            'device_id' => 'nullable|string',
            'device_nombre' => 'nullable|string',
            'multi_dispositivo' => 'nullable|boolean', // Frontend envía multi_dispositivo para multi_login
        ]);

        if (!$miembro->user) {
            return response()->json(['success' => false, 'message' => 'Miembro sin usuario asociado'], 400);
        }

        $miembro->user->update([
            'device_id' => $request->device_id,
            'device_nombre' => $request->device_nombre,
            'multi_login' => $request->get('multi_dispositivo', false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dispositivo actualizado correctamente',
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * MIEMBROS POR SECCIÓN
     * ═══════════════════════════════════════════════════════════
     */
    public function porSeccion(int $seccionId): JsonResponse
    {
        $miembros = Miembro::with(['categoria', 'user.roles'])
            ->whereHas('user', fn($q) => $q->where('activo', true))
            ->where('seccion_id', $seccionId)
            ->orderBy('apellidos')
            ->get()
            ->map(fn($m) => $this->transformMiembro($m));

        return response()->json([
            'success' => true,
            'data' => $miembros,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * EXTRACTO DEL MIEMBRO (Historial de pagos)
     * ═══════════════════════════════════════════════════════════
     */
    public function extracto(Request $request, Miembro $miembro): JsonResponse
    {
        $meses = $request->get('meses', 3);
        $fechaInicio = now()->subMonths($meses)->startOfMonth();

        $liquidaciones = $miembro->liquidaciones()
            ->with('evento')
            ->whereHas('evento', function ($q) use ($fechaInicio) {
                $q->where('fecha', '>=', $fechaInicio);
            })
            ->orderByDesc('created_at')
            ->get();

        $pagos = $miembro->pagos()
            ->where('fecha_pago', '>=', $fechaInicio)
            ->where('estado', 'procesado')
            ->orderByDesc('fecha_pago')
            ->get();

        $totalDevengado = $liquidaciones->sum('monto_final');
        $totalPagado = $pagos->sum('monto');
        $saldoPendiente = $totalDevengado - $totalPagado;

        return response()->json([
            'success' => true,
            'data' => [
                'miembro' => [
                    'id' => $miembro->id,
                    'nombre_completo' => $miembro->nombre_completo,
                ],
                'resumen' => [
                    'total_devengado' => $totalDevengado,
                    'total_pagado' => $totalPagado,
                    'saldo_pendiente' => $saldoPendiente,
                ],
                'liquidaciones' => $liquidaciones,
                'pagos' => $pagos,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════

    private function transformMiembro(Miembro $miembro): array
    {
        // Obtener rol principal del usuario
        $rolPrincipal = $miembro->user && $miembro->user->roles->isNotEmpty()
            ? $miembro->user->roles->sortByDesc('nivel')->first()
            : null;

        return [
            'id' => $miembro->id,
            'nombres' => $miembro->nombres,
            'apellidos' => $miembro->apellidos,
            'ci' => $miembro->ci_completo,
            'celular' => (string) $miembro->celular,
            'foto' => $miembro->foto,
            'fecha_nacimiento' => $miembro->fecha_nacimiento,
            'direccion' => $miembro->direccion,
            'latitud' => $miembro->latitud,
            'longitud' => $miembro->longitud,
            'referencia_nombre' => $miembro->referencia_nombre,
            'referencia_celular' => $miembro->referencia_celular,
            'nombre_completo' => $miembro->nombre_completo,
            'iniciales' => $miembro->iniciales,

            // Relaciones (Objetos completos)
            'seccion' => $miembro->seccion,
            'categoria' => $miembro->categoria,
            'rol' => $rolPrincipal,

            // IDs para formularios
            'seccion_id' => $miembro->seccion_id,
            'categoria_id' => $miembro->categoria_id,
            'rol_id' => $rolPrincipal ? $rolPrincipal->id : null,

            'activo' => $miembro->user ? $miembro->user->activo : false,
            'usuario' => $miembro->user ? $miembro->user->username : null,
            'multi_dispositivo' => $miembro->user ? $miembro->user->multi_login : false,
            'device_id' => $miembro->user ? $miembro->user->device_id : null,
        ];
    }

    private function generarUsuario(string $nombres, string $apellidos): string
    {
        $nombre = strtolower(explode(' ', trim($nombres))[0]);
        $apellido = strtolower(explode(' ', trim($apellidos))[0]);
        // Formato: 1ernombre.1erapellido
        $base = "{$nombre}.{$apellido}";
        $base = preg_replace('/[^a-z0-9.]/', '', $base);

        $usuario = $base;
        $contador = 1;
        while (\App\Models\User::where('username', $usuario)->exists()) {
            $usuario = $base . $contador;
            $contador++;
        }
        return $usuario;
    }

    private function generarPassword(): string
    {
        return substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 8);
    }
}
