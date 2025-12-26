<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Seccion;
use App\Models\CategoriaSalarial;
use App\Models\Rol;
use App\Models\Tarifa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CatalogoController extends Controller
{
    /**
     * ═══════════════════════════════════════════════════════════
     * SECCIONES
     * ═══════════════════════════════════════════════════════════
     */
    public function secciones(): JsonResponse
    {
        $secciones = Seccion::activas()
            ->ordenadas()
            ->withCount(['miembros' => function ($q) {
                $q->whereHas('user', fn($u) => $u->where('activo', true));
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $secciones,
        ]);
    }

    public function crearSeccion(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:secciones,nombre',
            'nombre_corto' => 'nullable|string|max:20',
            'icono' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'descripcion' => 'nullable|string',
            'es_viento' => 'nullable|boolean',
        ]);

        $seccion = Seccion::create($request->all());

        // Invalidar caché de catálogos
        cache()->forget('catalogos_todos');
        cache()->forget('dashboard_general');

        return response()->json([
            'success' => true,
            'message' => 'Sección creada correctamente',
            'data' => $seccion,
        ], 201);
    }

    public function actualizarSeccion(Request $request, Seccion $seccion): JsonResponse
    {
        $request->validate([
            'nombre' => 'sometimes|string|max:100|unique:secciones,nombre,' . $seccion->id,
            'nombre_corto' => 'nullable|string|max:20',
            'icono' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'descripcion' => 'nullable|string',
            'es_viento' => 'nullable|boolean',
            'orden' => 'nullable|integer',
            'activo' => 'nullable|boolean',
        ]);

        $seccion->update($request->all());

        // Invalidar caché de catálogos
        cache()->forget('catalogos_todos');
        cache()->forget('dashboard_general');

        return response()->json([
            'success' => true,
            'message' => 'Sección actualizada correctamente',
            'data' => $seccion,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * CATEGORÍAS SALARIALES
     * ═══════════════════════════════════════════════════════════
     */
    public function categorias(): JsonResponse
    {
        $categorias = CategoriaSalarial::activas()
            ->ordenadas()
            ->withCount(['miembros' => function ($q) {
                $q->whereHas('user', fn($u) => $u->where('activo', true));
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categorias,
        ]);
    }

    public function crearCategoria(Request $request): JsonResponse
    {
        $request->validate([
            'codigo' => 'required|string|size:1|unique:categorias_salariales,codigo',
            'nombre' => 'required|string|max:50',
            'descripcion' => 'nullable|string',
            'monto_base' => 'nullable|numeric|min:0',
        ]);

        $categoria = CategoriaSalarial::create($request->all());

        // Invalidar caché de catálogos
        cache()->forget('catalogos_todos');

        return response()->json([
            'success' => true,
            'message' => 'Categoría creada correctamente',
            'data' => $categoria,
        ], 201);
    }

    public function actualizarCategoria(Request $request, CategoriaSalarial $categoria): JsonResponse
    {
        $request->validate([
            'codigo' => 'sometimes|string|size:1|unique:categorias_salariales,codigo,' . $categoria->id,
            'nombre' => 'sometimes|string|max:50',
            'descripcion' => 'nullable|string',
            'monto_base' => 'nullable|numeric|min:0',
            'orden' => 'nullable|integer',
            'activo' => 'nullable|boolean',
        ]);

        $categoria->update($request->all());

        // Invalidar caché de catálogos
        cache()->forget('catalogos_todos');

        return response()->json([
            'success' => true,
            'message' => 'Categoría actualizada correctamente',
            'data' => $categoria,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * ROLES
     * ═══════════════════════════════════════════════════════════
     */
    public function roles(): JsonResponse
    {
        $roles = Rol::activos()
            ->ordenados()
            ->with('permisos')
            ->withCount(['miembros' => function ($q) {
                $q->whereHas('user', fn($u) => $u->where('activo', true));
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * TARIFAS
     * ═══════════════════════════════════════════════════════════
     */
    public function tarifas(): JsonResponse
    {
        $tarifas = Tarifa::with(['seccion', 'categoria'])->get();

        // Organizar por sección
        $porSeccion = $tarifas->groupBy('seccion_id')
            ->map(function ($items, $seccionId) {
                $seccion = $items->first()->seccion;
                return [
                    'seccion' => $seccion,
                    'tarifas' => $items->keyBy('categoria_id'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $porSeccion->values(),
        ]);
    }

    public function actualizarTarifa(Request $request): JsonResponse
    {
        $request->validate([
            'seccion_id' => 'required|exists:secciones,id',
            'categoria_id' => 'required|exists:categorias_salariales,id',
            'monto_ensayo' => 'required|numeric|min:0',
            'monto_contrato' => 'required|numeric|min:0',
        ]);

        $tarifa = Tarifa::updateOrCreate(
            [
                'seccion_id' => $request->seccion_id,
                'categoria_id' => $request->categoria_id,
            ],
            [
                'monto_ensayo' => $request->monto_ensayo,
                'monto_contrato' => $request->monto_contrato,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Tarifa actualizada correctamente',
            'data' => $tarifa->load(['seccion', 'categoria']),
        ]);
    }

    /**
     * ═══════════════════════════════════════════════════════════
     * TODOS LOS CATÁLOGOS (Para carga inicial) - OPTIMIZADO
     * ═══════════════════════════════════════════════════════════
     */
    public function todos(): JsonResponse
    {
        // Usar caché para catálogos que cambian poco
        $cacheKey = 'catalogos_todos';
        $cacheTTL = 300; // 5 minutos

        $data = cache()->remember($cacheKey, $cacheTTL, function () {
            return [
                'secciones' => Seccion::where('activo', true)
                    ->orderBy('orden')
                    ->get(['id', 'nombre', 'nombre_corto', 'icono', 'color']),
                'categorias' => CategoriaSalarial::where('activo', true)
                    ->orderBy('orden')
                    ->get(['id', 'codigo', 'nombre', 'monto_base']),
                'roles' => Rol::where('activo', true)
                    ->orderBy('nivel', 'desc')
                    ->get(['id', 'nombre', 'slug', 'nivel']),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
