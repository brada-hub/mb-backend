<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Models\DispositivoAutorizado;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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

        // 4. Device Bonding (Only for Mobile)
        $isMobilePlatform = in_array($request->platform, ['mobile', 'android', 'ios']);
        if ($isMobilePlatform && $request->uuid_celular) {

            // SEGURIDAD: Validar que el dispositivo no esté configurado con otro usuario
            $dispositivoAjeno = DispositivoAutorizado::where('uuid_celular', $request->uuid_celular)
                                                      ->where('id_user', '!=', $user->id_user)
                                                      ->first();

            if ($dispositivoAjeno) {
                return response()->json([
                    'message' => 'Este dispositivo ya está vinculado a otra cuenta. Contacta a soporte para liberarlo.'
                ], 403);
            }

            // Verificar si este dispositivo ya está registrado para este usuario
            $currentDevice = DispositivoAutorizado::where('id_user', $user->id_user)
                                                  ->where('uuid_celular', $request->uuid_celular)
                                                  ->first();

            if ($currentDevice) {
                if (!$currentDevice->estado) {
                    return response()->json(['message' => 'Este dispositivo ha sido bloqueado. Contacte al administrador.'], 403);
                }
                // Actualizar token FCM
                if ($request->fcm_token) {
                    $currentDevice->update(['fcm_token' => $request->fcm_token]);
                }
            } else {
                // Nuevo dispositivo. Validar límite
                if (!$user->isSuperAdmin()) {
                    $count = DispositivoAutorizado::where('id_user', $user->id_user)->count();
                    $limit = $user->limite_dispositivos ?? 2;

                    if ($count >= $limit) {
                        return response()->json([
                            'message' => "Límite de dispositivos alcanzado ({$limit}). Contacta a tu Director para habilitar más accesos."
                        ], 403);
                    }
                }

                // Registrar nuevo dispositivo
                DispositivoAutorizado::create([
                    'id_user' => $user->id_user,
                    'uuid_celular' => $request->uuid_celular,
                    'nombre_modelo' => $request->device_model ?? 'Dispositivo Móvil',
                    'fcm_token' => $request->fcm_token,
                    'estado' => true
                ]);
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


        $token = $user->createToken($request->platform . '-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('miembro.rol', 'miembro.permisos', 'miembro.instrumento', 'miembro.categoria'),
            'role' => $roleName,
            'permissions' => $allPermissions,
            'password_changed' => $user->password_changed,
            'profile_completed' => $user->profile_completed,
            'is_super_admin' => false,
            'streak' => $user->miembro ? $user->miembro->calculateStreak() : 0
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

        $user->load('miembro.rol.permisos', 'miembro.permisos', 'miembro.seccion', 'miembro.instrumento', 'miembro.categoria', 'banda');

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
            'profile_completed' => $user->profile_completed,
            'streak' => $user->miembro->calculateStreak()
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

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta'], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'password_changed' => true
        ]);

        // Revocar otros dispositivos por seguridad
        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    public function syncMasterData()
    {
        $user = auth()->user();
        if (!$user) return response()->json([], 401);

        $idBanda = $user->id_banda;

        // Cache for 1 hour
        $data = Cache::remember("master_data_banda_{$idBanda}", 3600, function() use ($user) {
            return [
                'roles' => \App\Models\Rol::whereNotIn('rol', ['ADMIN', 'SUPER_ADMIN'])->get(['id_rol', 'rol']),
                'secciones' => \App\Models\Seccion::with('instrumentos:id_instrumento,id_seccion,instrumento')->get(['id_seccion', 'seccion']),
                'categorias' => \App\Models\Categoria::get(['id_categoria', 'nombre_categoria']),
                'permisos' => \App\Models\Permiso::get(['id_permiso', 'permiso']),
                'voces' => \App\Models\VozInstrumental::all(['id_voz', 'nombre_voz']),
                'suscripcion' => $user->id_banda ? [
                    'plan' => $user->banda?->plan ?? 'BASIC',
                    'max_miembros' => $user->banda?->max_miembros ?? 15,
                    'uso_miembros' => \App\Models\Miembro::count(),
                    'pro_activo' => in_array(strtoupper($user->banda?->plan ?? ''), ['PREMIUM', 'PRO', 'MONSTER'])
                ] : null,
            ];
        });

        return response()->json($data);
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
        $request->validate([
            'fcm_token' => 'required|string',
            'uuid_celular' => 'nullable|string'
        ]);

        $user = $request->user();

        if ($request->uuid_celular) {
            // Actualizar Token en el dispositivo específico
            DispositivoAutorizado::where('id_user', $user->id_user)
                ->where('uuid_celular', $request->uuid_celular)
                ->update(['fcm_token' => $request->fcm_token]);
        } else {
            // Fallback: Guardar en el user para compatibilidad
            $user->fcm_token = $request->fcm_token;
            $user->save();
        }

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
