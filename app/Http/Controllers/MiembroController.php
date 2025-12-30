<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreMiembroRequest;
use App\Models\Miembro;
use App\Models\User;

class MiembroController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Miembro::with(['categoria', 'seccion', 'rol'])->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMiembroRequest $request)
    {
        return \DB::transaction(function () use ($request) {
            // 1. Create Miembro (Expediente)
            $miembro = Miembro::create($request->only([
                'id_categoria', 'id_seccion', 'id_rol', 'nombres', 'apellidos',
                'ci', 'celular', 'fecha', 'latitud', 'longitud', 'direccion'
            ]));

            // 2. Create User Login (if requested)
            if ($request->create_user) {
                User::create([
                    'user' => $request->username,
                    'password' => \Hash::make($request->password), // Password hashed
                    'id_miembro' => $miembro->id_miembro,
                    'estado' => true
                ]);
            }

            // 3. Create Contacto de Emergencia (if data exists)
            if ($request->filled('contacto_nombre')) {
                $miembro->contactos()->create([
                    'nombres_apellidos' => $request->contacto_nombre,
                    'parentesco' => $request->contacto_parentesco,
                    'celular' => $request->contacto_celular
                ]);
            }

            return response()->json($miembro->load('user', 'contactos'), 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Miembro::with(['user', 'contactos'])->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $miembro = Miembro::findOrFail($id);

        $validated = $request->validate([
            'nombres' => 'string|max:50',
            'celular' => 'integer',
            'direccion' => 'nullable|string',
            'version_perfil' => 'integer'
        ]);

        $miembro->update($validated);
        return response()->json($miembro);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Miembro::destroy($id);
        return response()->json(null, 204);
    }
}
