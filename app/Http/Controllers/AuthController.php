<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Models\DispositivoAutorizado;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::with(['miembro.rol.permisos', 'miembro.permisos', 'banda'])->where('user', $request->user)->first();

        // 1. Basic Auth Check
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        if (!$user->estado) return response()->json(['message' => 'Usuario inactivo'], 403);

        // Validation: If login via band portal, ensure user belongs to that band
        if ($request->band_slug && !$user->isSuperAdmin()) {
            if (!$user->banda || $user->banda->slug !== $request->band_slug) {
                return response()->json(['message' => 'Acceso denegado: Este usuario no pertenece a esta organización.'], 403);
            }
        }

        // 2. Super Admin bypass - no necesita miembro
        if ($user->isSuperAdmin()) {
            $token = $user->createToken($request->platform . '-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user,
                'role' => 'ADMIN',
                'permissions' => ['*'], // Acceso total
                'password_changed' => $user->password_changed,
                'profile_completed' => $user->profile_completed,
                'is_super_admin' => true
            ]);
        }

        // 3. Permissions Calculation (Early calculation for Gatekeeping)
        $rolePerms = $user->miembro->rol->permisos->pluck('permiso')->toArray();
        $customPerms = $user->miembro->permisos->pluck('permiso')->toArray();
        $allPermissions = array_values(array_unique(array_merge($rolePerms, $customPerms)));

        // 4. Role/Platform Discrimination
        $roleName = $user->miembro->rol->rol ?? 'Desconocido';

        if ($request->platform === 'web') {
            if (!$user->id_miembro) {
                 return response()->json(['message' => 'Acceso denegado: Usuario sin perfil.'], 403);
            }
            if (!in_array('ACCESO_WEB', $allPermissions)) {
                return response()->json(['message' => 'Acceso denegado: No tienes permisos para ingresar a la versión Web. Usa la App Móvil.'], 403);
            }
        }

        // 4. Device Bonding (Only for Mobile)
        if ($request->platform === 'mobile' && $request->uuid_celular) {
            // Check if THIS specific device is already registered
            $currentDevice = DispositivoAutorizado::where('id_user', $user->id_user)
                                                  ->where('uuid_celular', $request->uuid_celular)
                                                  ->first();

            if ($currentDevice) {
                // Device exists. Check if it's explicitly blocked.
                if (!$currentDevice->estado) {
                    return response()->json(['message' => 'Este dispositivo ha sido bloqueado. Contacte al administrador.'], 403);
                }
            } else {
                // New device. Check dynamic limit
                $count = DispositivoAutorizado::where('id_user', $user->id_user)->count();
                $limit = $user->limite_dispositivos ?? 1; // Default to 1 if null

                if ($count >= $limit) {
                    return response()->json(['message' => "Límite de dispositivos alcanzado ({$limit}). Solicita más accesos o elimina uno antiguo."], 403);
                }

                // Auto-register new device
                DispositivoAutorizado::create([
                    'id_user' => $user->id_user,
                    'uuid_celular' => $request->uuid_celular,
                    'nombre_modelo' => $request->device_model ?? 'Dispositivo Móvil',
                    'estado' => true // Active by default
                ]);
            }
        }

        $token = $user->createToken($request->platform . '-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('miembro.rol', 'miembro.permisos'),
            'role' => $roleName,
            'permissions' => $allPermissions,
            'password_changed' => $user->password_changed,
            'profile_completed' => $user->profile_completed,
            'is_super_admin' => false
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function checkDevice(Request $request)
    {
        $request->validate([
            'uuid_celular' => 'required',
            'user' => 'required'
        ]);

        $user = User::where('user', $request->user)->first();
        if (!$user) return response()->json(['authorized' => false], 404);

        $exists = DispositivoAutorizado::where('uuid_celular', $request->uuid_celular)
                    ->where('id_user', $user->id_user)
                    ->where('estado', true)
                    ->exists();

        return response()->json(['authorized' => $exists]);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        // Si es Super Admin, devolver respuesta especial
        if ($user->isSuperAdmin()) {
            return response()->json([
                'user' => $user,
                'role' => 'ADMIN',
                'permissions' => ['*'],
                'password_changed' => $user->password_changed,
                'profile_completed' => $user->profile_completed,
                'is_super_admin' => true
            ]);
        }

        $user->load('miembro.rol.permisos', 'miembro.permisos', 'miembro.seccion', 'banda');

        // Validar que tenga miembro asociado
        if (!$user->miembro) {
             return response()->json(['message' => 'Perfil de miembro no encontrado'], 500);
        }

        $rolePerms = $user->miembro->rol?->permisos->pluck('permiso')->toArray() ?? [];
        $customPerms = $user->miembro->permisos->pluck('permiso')->toArray();
        $allPermissions = array_values(array_unique(array_merge($rolePerms, $customPerms)));

        return response()->json([
            'user' => $user,
            'role' => $user->miembro->rol->rol ?? 'Sin Rol',
            'permissions' => $allPermissions,
            'password_changed' => $user->password_changed,
            'profile_completed' => $user->profile_completed
        ]);
    }

    public function syncVersions()
    {
        // Return latest versions of catalogs
        return response()->json([
            'version_perfil' => 1,
            'version_eventos' => 1,
            // Logic to fetch real max versions from DB
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($request->password),
            'password_changed' => true
        ]);

        // Solo revocar OTROS tokens, no el actual (para mantener la sesión viva)
        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente',
            'password_changed' => true,
            'profile_completed' => $user->profile_completed
        ]);
    }

    public function syncMasterData()
    {
        $user = auth()->user();
        if (!$user) return response()->json([], 401);

        // Return all small catalogs
        return response()->json([
            'roles' => \App\Models\Rol::whereNotIn('rol', ['ADMIN', 'SUPER_ADMIN'])->get(),
            'secciones' => \App\Models\Seccion::with('instrumentos')->get(),
            'categorias' => \App\Models\Categoria::all(),
            'permisos' => \App\Models\Permiso::all(),
            'voces' => \App\Models\VozInstrumental::all(),
            'suscripcion' => $user->id_banda ? [
                'plan' => $user->banda?->plan ?? 'BASIC',
                'max_miembros' => $user->banda?->max_miembros ?? 15,
                'uso_miembros' => \App\Models\Miembro::count(), // Scoped by BelongsToBanda trait
                'pro_activo' => in_array(strtoupper($user->banda?->plan), ['PREMIUM', 'PRO', 'MONSTER'])
            ] : null,
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $request->validate([
            'preferences' => 'required|array'
        ]);

        $user = $request->user();
        $user->preferencias_notificaciones = $request->preferences;
        $user->save();

        return response()->json(['status' => 'ok', 'preferences' => $user->preferencias_notificaciones]);
    }

    public function updateFCMToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);
        $user = $request->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json(['status' => 'ok']);
    }

    public function updateTheme(Request $request)
    {
        $request->validate(['theme' => 'required|in:light,dark,system']);
        $user = $request->user();
        $user->theme_preference = $request->theme;
        $user->save();

        return response()->json(['status' => 'ok']);
    }

    public function completeProfile(Request $request)
    {
        $user = auth()->user();
        if (!$user->miembro) return response()->json(['message' => 'No profile found'], 404);

        $request->validate([
            'nombres' => 'required|string|max:50',
            'apellidos' => 'required|string|max:50',
            'ci' => 'required|string|max:20',
            'celular' => 'required|string|max:15',
            'password' => 'nullable|string|min:8|confirmed',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'direccion' => 'nullable|string',
            'fecha' => 'nullable|date',
            'has_emergency_contact' => 'nullable|boolean',
            'contacto_nombre' => 'required_if:has_emergency_contact,true|string|max:100',
            'contacto_celular' => 'required_if:has_emergency_contact,true|string|max:15',
            'contacto_parentesco' => 'nullable|string|max:50',
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user) {
            $miembro = $user->miembro;

            // Actualizar datos de miembro
            $miembro->update($request->only([
                'nombres', 'apellidos', 'ci', 'celular', 'latitud', 'longitud', 'direccion', 'fecha'
            ]));

            // Manejar contacto de emergencia
            if ($request->has_emergency_contact) {
                $miembro->contactos()->updateOrCreate(
                    ['id_miembro' => $miembro->id_miembro],
                    [
                        'nombres_apellidos' => $request->contacto_nombre,
                        'celular' => $request->contacto_celular,
                        'parentesco' => $request->contacto_parentesco
                    ]
                );
            }

            // Actualizar usuario
            $updateData = [
                'password_changed' => true,
                'profile_completed' => true
            ];

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            return response()->json([
                'message' => 'Perfil configurado con éxito',
                'user' => $user->load('miembro.rol', 'banda')
            ]);
        });
    }
}
