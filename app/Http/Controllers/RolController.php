<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Permiso;
use Illuminate\Http\Request;

class RolController extends Controller
{
    public function index()
    {
        return Rol::with('permisos')
            ->whereNotIn('rol', ['ADMIN', 'SUPER_ADMIN'])
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rol' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'permisos' => 'array'
        ]);

        $rol = Rol::create([
            'rol' => mb_strtoupper($validated['rol'], 'UTF-8'),
            'descripcion' => $validated['descripcion'] ? mb_strtoupper($validated['descripcion'], 'UTF-8') : null,
            'es_protegido' => false // Solo SuperAdmin puede crear protegidos vía seeder
        ]);

        if (isset($validated['permisos'])) {
            $rol->permisos()->sync($validated['permisos']);
        }

        return response()->json($rol->load('permisos'), 201);
    }

    public function show(string $id)
    {
        $rol = Rol::with('permisos')->findOrFail($id);
        if (in_array($rol->rol, ['ADMIN', 'SUPER_ADMIN'])) abort(404);
        return $rol;
    }

    public function update(Request $request, string $id)
    {
        $rol = Rol::findOrFail($id);

        if ($rol->es_protegido) {
            return response()->json(['message' => 'Este es un rol de fábrica y no puede ser modificado.'], 403);
        }

        $validated = $request->validate([
            'rol' => 'string|max:255',
            'descripcion' => 'nullable|string',
            'permisos' => 'array'
        ]);

        $updateData = [];
        if ($request->has('rol')) {
            $updateData['rol'] = mb_strtoupper($request->rol, 'UTF-8');
        }
        if ($request->has('descripcion')) {
            $updateData['descripcion'] = $request->descripcion ? mb_strtoupper($request->descripcion, 'UTF-8') : null;
        }

        $rol->update($updateData);

        if (isset($validated['permisos'])) {
            $rol->permisos()->sync($validated['permisos']);
        }

        return response()->json($rol->load('permisos'));
    }

    public function destroy(string $id)
    {
        $rol = Rol::findOrFail($id);

        if ($rol->es_protegido) {
            return response()->json(['message' => 'Este es un rol de fábrica y no puede ser eliminado.'], 403);
        }

        if ($rol->miembros()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el rol porque tiene miembros activos asociados.'
            ], 422);
        }

        $rol->permisos()->detach(); // Clean up pivot table
        $rol->delete();

        return response()->json(null, 204);
    }

    public function getPermisos()
    {
        return Permiso::all();
    }
}
