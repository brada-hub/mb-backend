<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Requests\StoreMiembroRequest;
use App\Http\Requests\UpdateMiembroRequest;
use App\Models\Miembro;
use App\Models\User;

class MiembroController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Miembro::with(['categoria', 'seccion', 'rol.permisos', 'user', 'contactos', 'permisos'])->get();
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

            // 2. Automatic User Generation Logic
            $firstName = Str::lower(explode(' ', trim($request->nombres))[0]);
            $lastName = Str::lower(explode(' ', trim($request->apellidos))[0]);
            $generatedUsername = "{$firstName}.{$lastName}@mb";

            // Password: First 2 letters of name + first 2 of surname + mb2026
            $passPart1 = Str::substr($firstName, 0, 2);
            $passPart2 = Str::substr($lastName, 0, 2);
            $generatedPassword = "{$passPart1}{$passPart2}mb2026";

            $user = User::create([
                'user' => $generatedUsername,
                'password' => \Hash::make($generatedPassword),
                'id_miembro' => $miembro->id_miembro,
                'estado' => true
            ]);

            // 3. Create Contacto de Emergencia (if data exists)
            if ($request->has_emergency_contact && $request->filled('contacto_nombre')) {
                $miembro->contactos()->create([
                    'nombres_apellidos' => $request->contacto_nombre,
                    'parentesco' => $request->contacto_parentesco,
                    'celular' => $request->contacto_celular
                ]);
            }

            // 4. Personalized Permissions
            if ($request->has('permisos')) {
                $miembro->permisos()->sync($request->permisos);
            }

            return response()->json([
                'miembro' => $miembro->load('user', 'contactos', 'seccion', 'categoria', 'rol', 'permisos'),
                'credentials' => [
                    'username' => $generatedUsername,
                    'password' => $generatedPassword,
                    'whatsapp_url' => "https://wa.me/591{$miembro->celular}?text=" . urlencode("¡Hola {$miembro->nombres}! Bienvenido a Monster Band. 👹\n\nTu cuenta ha sido creada:\n👤 Usuario: {$generatedUsername}\n🔐 Contraseña: {$generatedPassword}\n\nDescarga la app y accede ahora.")
                ]
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Miembro::with(['user', 'contactos', 'permisos', 'rol.permisos'])->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMiembroRequest $request, string $id)
    {
        $miembro = Miembro::findOrFail($id);

        $miembro->update($request->only([
            'id_categoria', 'id_seccion', 'id_rol', 'nombres', 'apellidos',
            'ci', 'celular', 'fecha', 'latitud', 'longitud', 'direccion'
        ]));

        // Manejar contacto de emergencia: solo si viene en el request
        if ($request->has('has_emergency_contact')) {
            $miembro->contactos()->delete();
            if ($request->has_emergency_contact && $request->filled('contacto_nombre')) {
                $miembro->contactos()->create([
                    'nombres_apellidos' => $request->contacto_nombre,
                    'parentesco' => $request->contacto_parentesco,
                    'celular' => $request->contacto_celular
                ]);
            }
        }

        // Manejar permisos personalizados
        if ($request->has('permisos')) {
            $miembro->permisos()->sync($request->permisos);
        }

        return response()->json($miembro->load('user', 'contactos', 'seccion', 'categoria', 'rol.permisos', 'permisos'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Miembro::destroy($id);
        return response()->json(null, 204);
    }

    public function toggleStatus(string $id)
    {
        $miembro = Miembro::with('user')->findOrFail($id);
        if ($miembro->user) {
            $miembro->user->update([
                'estado' => !$miembro->user->estado
            ]);
        }
        return response()->json($miembro->load('user', 'rol.permisos', 'seccion', 'categoria', 'permisos'));
    }

    public function cleanupTestMember()
    {
        // El CI que usamos en la prueba de Cypress
        $ci = '11223344';
        $miembro = Miembro::where('ci', $ci)->first();

        if ($miembro) {
            // Borrar usuario asociado si existe
            if ($miembro->user) {
                $miembro->user->delete();
            }
            // Borrar el miembro
            $miembro->delete();
            return response()->json(['message' => 'Test member cleaned up']);
        }

        return response()->json(['message' => 'No test member found']);
    }
}
