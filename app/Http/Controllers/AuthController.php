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

        if ($request->platform === 'web' && $roleName !== 'Director' && $roleName !== 'Admin') {
            return response()->json(['message' => 'Acceso denegado: Solo Directores pueden acceder al Panel Web.'], 403);
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

        return response()->json([
            'token' => $token,
            'user' => $user, // Modified response structure
            'role' => $roleName,
            'permissions' => [] // Load permissions logic here
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
        return response()->json($request->user()->load('miembro.rol', 'miembro.seccion'));
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

    public function syncMasterData()
    {
        // Return all small catalogs
        return response()->json([
            'roles' => \App\Models\Rol::all(),
            'secciones' => \App\Models\Seccion::all(),
            'categorias' => \App\Models\Categoria::all(),
             // ... others
        ]);
    }
}
