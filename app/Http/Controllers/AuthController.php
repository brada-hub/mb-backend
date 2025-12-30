<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\DispositivoAutorizado;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'user' => 'required',
            'password' => 'required',
            'uuid_celular' => 'nullable' // For device check on login
        ]);

        $user = User::where('user', $request->user)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        if (!$user->estado) {
            return response()->json(['message' => 'Usuario inactivo'], 403);
        }

        // Optional: Device Bonding Check
        if ($request->uuid_celular) {
            $device = DispositivoAutorizado::where('uuid_celular', $request->uuid_celular)
                        ->where('id_user', $user->id_user)
                        ->first();

            if (!$device) {
                 // Or auto-register based on logic
                 // return response()->json(['message' => 'Dispositivo no autorizado'], 403);
            }
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'id_miembro' => $user->id_miembro,
            'user' => $user
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
