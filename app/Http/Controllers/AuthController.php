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
    public function login(LoginRequest $request) // Changed Request to LoginRequest
    {
        $user = User::with('miembro.rol')->where('user', $request->user)->first(); // Modified user retrieval

        // 1. Basic Auth Check
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        if (!$user->estado) return response()->json(['message' => 'Usuario inactivo'], 403);

        // 2. Role Discrimination (Portal vs App)
        $roleName = $user->miembro->rol->rol ?? 'Desconocido';

        if ($request->platform === 'web' && !$user->id_miembro) {
            return response()->json(['message' => 'Acceso denegado: Este usuario no tiene un perfil de miembro asociado.'], 403);
        }

        // 3. Device Bonding (Only for Mobile)
        if ($request->platform === 'mobile' && $request->uuid_celular) {
            $device = DispositivoAutorizado::where('id_user', $user->id_user)->first(); // Assuming 1 device per user for strict mode

            if ($device) {
                // If device registered, match UUID
                if ($device->uuid_celular !== $request->uuid_celular && $device->estado) {
                    return response()->json(['message' => 'Este usuario está vinculado a otro dispositivo. Contacte al Director.'], 403);
                }
            } else {
                // Auto-register first device (Bonding)
                DispositivoAutorizado::create([
                    'id_user' => $user->id_user,
                    'uuid_celular' => $request->uuid_celular,
                    'nombre_modelo' => $request->device_model ?? 'Desconocido'
                ]);
            }
        }

        $token = $user->createToken($request->platform . '-token')->plainTextToken; // Modified token name

        // 4. Permissions Logic
        $rolePerms = $user->miembro->rol->permisos->pluck('permiso')->toArray();
        $customPerms = $user->miembro->permisos->pluck('permiso')->toArray();
        $allPermissions = array_unique(array_merge($rolePerms, $customPerms));

        return response()->json([
            'token' => $token,
            'user' => $user->load('miembro.rol', 'miembro.permisos'),
            'role' => $roleName,
            'permissions' => $allPermissions,
            'password_changed' => $user->password_changed
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
        $user = $request->user()->load('miembro.rol.permisos', 'miembro.permisos', 'miembro.seccion');

        $rolePerms = $user->miembro->rol->permisos->pluck('permiso')->toArray();
        $customPerms = $user->miembro->permisos->pluck('permiso')->toArray();
        $allPermissions = array_unique(array_merge($rolePerms, $customPerms));

        return response()->json([
            'user' => $user,
            'role' => $user->miembro->rol->rol,
            'permissions' => $allPermissions,
            'password_changed' => $user->password_changed
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

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    public function syncMasterData()
    {
        // Return all small catalogs
        return response()->json([
            'roles' => \App\Models\Rol::all(),
            'secciones' => \App\Models\Seccion::all(),
            'categorias' => \App\Models\Categoria::all(),
            'permisos' => \App\Models\Permiso::all(),
             // ... others
        ]);
    }
}
