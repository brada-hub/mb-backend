<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Requests\StoreMiembroRequest;
use App\Http\Requests\UpdateMiembroRequest;
use App\Models\Miembro;
use App\Models\User;
use App\Models\DispositivoAutorizado;

class MiembroController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        $query = Miembro::with(['categoria', 'seccion', 'instrumento', 'voz', 'rol.permisos', 'user', 'contactos', 'permisos']);

        // 1. Si es SuperAdmin (Admin Global), no aplicamos más filtros
        if ($user->isSuperAdmin() && empty($user->id_banda)) {
            return $query->get();
        }

        // NUEVO: Excluir SuperAdmins de la lista para usuarios normales
        $query->whereHas('user', function($q) {
            $q->where('is_super_admin', false)->orWhereNull('is_super_admin');
        });

        $rol = ($user->miembro->rol->rol ?? '') ? strtoupper($user->miembro->rol->rol) : '';

        // 2. Si es Jefe de Sección (Delegado/Jefe)
        if (Str::contains($rol, ['JEFE', 'DELEGADO'])) {
            $query->where('id_instrumento', $user->miembro->id_instrumento);
        }

        // 3. Si es Músico
        if (Str::contains($rol, 'MÚSICO')) {
            $query->where('id_miembro', $user->id_miembro);
        }

        // 4. Si es Director, no filtramos más (ve toda la banda por el scope global)
        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMiembroRequest $request)
    {
        // Validar límite de miembros del plan SaaS
        $user = \Auth::user();
        if (!$user->isSuperAdmin()) {
            $banda = $user->banda;
            if ($banda && $banda->haAlcanzadoLimiteMiembros()) {
                return response()->json([
                    'message' => 'Has alcanzado el límite de tu plan. Mejora a un plan superior para registrar más músicos.'
                ], 403);
            }
        }

        return \DB::transaction(function () use ($request, $user) {
            // 1. Create Miembro (Expediente)
            $data = $request->only([
                'id_categoria', 'id_seccion', 'id_instrumento', 'id_voz', 'id_rol', 'nombres', 'apellidos',
                'ci', 'celular', 'fecha', 'latitud', 'longitud', 'direccion', 'referencia_vivienda'
            ]);

            // Asignar banda al miembro (importante)
            if ($user->id_banda) {
                $data['id_banda'] = $user->id_banda;
            }

            $miembro = Miembro::create($data);

            // 2. Automatic User Generation Logic
            $generatedUsername = $request->ci; // Username = CI
            $generatedPassword = $request->ci; // Password = CI

            $newUser = User::create([
                'user' => $generatedUsername,
                'password' => \Hash::make($generatedPassword),
                'id_miembro' => $miembro->id_miembro,
                'id_banda' => $user->id_banda, // Vincular user a la banda
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
            'id_categoria', 'id_seccion', 'id_instrumento', 'id_voz', 'id_rol', 'nombres', 'apellidos',
            'ci', 'celular', 'fecha', 'latitud', 'longitud', 'direccion', 'referencia_vivienda'
        ]));

        // Manejar contacto de emergencia
        if ($request->has('has_emergency_contact')) {
            $miembro->contactos()->delete();
            // Verifica si es booleano o string 'true'/'1'
            $hasContact = filter_var($request->has_emergency_contact, FILTER_VALIDATE_BOOLEAN);

            if ($hasContact && $request->filled('contacto_nombre')) {
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

    // --- Device Management Methods ---

    public function getDevices(string $id)
    {
        $miembro = Miembro::with('user.dispositivos')->findOrFail($id);
        if (!$miembro->user) {
            return response()->json(['devices' => [], 'limit' => 1]);
        }

        return response()->json([
            'devices' => $miembro->user->dispositivos,
            'limit' => $miembro->user->limite_dispositivos ?? 1
        ]);
    }

    public function updateDeviceLimit(Request $request, string $id)
    {
        $request->validate(['limit' => 'required|integer|min:1|max:10']);

        $miembro = Miembro::with('user')->findOrFail($id);

        if ($miembro->user) {
            $miembro->user->update(['limite_dispositivos' => $request->limit]);
        }

        return response()->json(['message' => 'Límite actualizado', 'limit' => $request->limit]);
    }

    public function deleteDevice(string $deviceId)
    {
        $device = DispositivoAutorizado::findOrFail($deviceId);
        $device->delete();
        return response()->json(['message' => 'Dispositivo eliminado']);
    }

    public function resetPassword(string $id)
    {
        $miembro = Miembro::with('user')->findOrFail($id);

        if (!$miembro->user) {
            return response()->json(['message' => 'Este miembro no tiene usuario asociado'], 404);
        }

        // Create new password based on CI if available, or keep old logic if CI not easily accessible (though it should be)
        // User requested: "el usuario sera siempre el ci... la contrasena sera el ci inicialmente"
        // For reset, it makes sense to reset to CI as well if that's the "initial" state they want to revert to.
        $newPassword = $miembro->ci;

        $miembro->user->update([
            'password' => \Hash::make($newPassword),
            'password_changed' => false
        ]);

        // Revocar todas las sesiones actives para forzar re-login
        $miembro->user->tokens()->delete();

        // Generate Whatsapp link
        $whatsappUrl = "https://wa.me/591{$miembro->celular}?text=" . urlencode("Hola {$miembro->nombres}, tu contraseña ha sido restablecida. 🔐\n\nNueva contraseña temporal: {$newPassword}\n\nIngresa a la app y cámbiala por seguridad.");

        return response()->json([
            'message' => 'Contraseña restablecida',
            'new_password' => $newPassword,
            'whatsapp_url' => $whatsappUrl
        ]);
    }
}
