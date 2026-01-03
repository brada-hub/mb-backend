<?php

namespace App\Http\Controllers;

use App\Models\Seccion;
use App\Models\Rol;
use Illuminate\Http\Request;

class SeccionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // El usuario pidió que el borrado sea lógico, así que por defecto solo mostramos las activas
        return Seccion::with('instrumentos')->where('estado', true)->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'seccion' => 'required|string|max:255',
            'descripcion' => 'nullable|string'
        ]);

        $nombreSeccion = mb_strtoupper($request->seccion, 'UTF-8');

        // Check if exists independently of status
        $existing = Seccion::where('seccion', $nombreSeccion)->first();

        if ($existing) {
            if ($existing->estado) {
                return response()->json([
                    'message' => 'The seccion has already been taken.',
                    'errors' => ['seccion' => ['El nombre ya existe.']]
                ], 422);
            } else {
                // Reactivate and update
                $existing->update([
                    'estado' => true,
                    'descripcion' => $request->descripcion ? mb_strtoupper($request->descripcion, 'UTF-8') : null
                ]);
                return response()->json($existing, 201);
            }
        }

        $seccion = Seccion::create([
            'seccion' => $nombreSeccion,
            'descripcion' => $request->descripcion ? mb_strtoupper($request->descripcion, 'UTF-8') : null,
            'estado' => true
        ]);

        return response()->json($seccion, 201);
    }

    public function show(string $id)
    {
        return Seccion::findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $seccion = Seccion::findOrFail($id);

        $request->validate([
            'seccion' => 'string|max:255',
            'descripcion' => 'nullable|string'
        ]);

        if ($request->has('seccion')) {
            $nombreSeccion = mb_strtoupper($request->seccion, 'UTF-8');

            // Check uniqueness ignoring current ID but checking others
            $existing = Seccion::where('seccion', $nombreSeccion)
                ->where('id_seccion', '!=', $id)
                ->first();

            if ($existing) {
                if ($existing->estado) {
                    return response()->json([
                        'message' => 'The seccion has already been taken.',
                        'errors' => ['seccion' => ['El nombre ya existe.']]
                    ], 422);
                }
                // If exists but deleted, we can't just take it because we are UPDATING another record.
                // We should technically allow it, but we can't merge two records easily.
                // So for simplicity in UPDATE, we will block if a deleted record exists to avoid duplication chaos.
                // OR we could say "Name taken by a deleted record".
                // Let's standard block for now to keep integrity.
                return response()->json([
                     'message' => 'El nombre pertenece a una sección eliminada anteriormente.',
                     'errors' => ['seccion' => ['Nombre en uso por sección archivada.']]
                ], 422);
            }

            $seccion->seccion = $nombreSeccion;
        }

        if ($request->has('descripcion')) {
            $seccion->descripcion = $request->descripcion ? mb_strtoupper($request->descripcion, 'UTF-8') : null;
        }

        $seccion->save();

        return response()->json($seccion);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $seccion = Seccion::findOrFail($id);

        // 1. Verificar si tiene instrumentos asociados
        if ($seccion->instrumentos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la sección porque tiene instrumentos registrados. Elimina primero los instrumentos.'
            ], 422);
        }

        // 2. Verificar si existen miembros asociados a esta sección
        if ($seccion->miembros()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la sección porque tiene miembros activos asociados.'
            ], 422);
        }

        // El usuario pidió que no se elimine de la BD, solo que no se vea
        $seccion->update(['estado' => false]);

        return response()->json(['message' => 'Sección eliminada correctamente']);
    }

    public function cleanupTestData()
    {
        // Limpiar Roles de prueba
        Rol::where('rol', 'LIKE', 'ROL CYPRESS%')->get()->each(function($rol) {
            $rol->permisos()->detach();
            $rol->delete();
        });

        // Limpiar Secciones de prueba
        Seccion::where('seccion', 'LIKE', 'SECCION CYPRESS%')->delete();

        return response()->json(['message' => 'Test data cleaned up']);
    }
}
