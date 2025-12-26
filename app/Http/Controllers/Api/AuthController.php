<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Miembro;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'usuario' => 'required|string',
            'password' => 'required|string',
            'device_id' => 'nullable|string',
            'device_nombre' => 'nullable|string',
        ]);

        $user = \App\Models\User::where('username', $request->usuario)
            ->where('activo', true)
            ->with(['miembro', 'roles'])
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'usuario' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Verificar dispositivo
        if (!$user->verificarDispositivo($request->device_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Este dispositivo no está autorizado. Contacte al administrador.',
                'error_code' => 'DEVICE_NOT_AUTHORIZED',
            ], 403);
        }

        // Registrar dispositivo si es el primero
        if ($request->device_id) {
            $user->registrarDispositivo($request->device_id, $request->device_nombre);
        }

        // Crear token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'data' => [
                'token' => $token,
                'miembro' => $this->formatUserMiembro($user),
                'requiere_cambio_password' => $user->cambio_password_requerido,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente',
        ]);
    }

    public function cambiarPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password_actual' => 'required|string',
            'password_nueva' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password_actual, $user->password)) {
            throw ValidationException::withMessages([
                'password_actual' => ['La contraseña actual es incorrecta.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password_nueva),
            'cambio_password_requerido' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente',
        ]);
    }

    public function perfil(Request $request): JsonResponse
    {
        // Cargar relaciones necesarias
        $user = $request->user()->load(['miembro.seccion', 'miembro.categoria', 'roles']);

        return response()->json([
            'success' => true,
            'data' => $this->formatUserMiembro($user),
        ]);
    }

    public function actualizarPerfil(Request $request): JsonResponse
    {
        $request->validate([
            'celular' => 'nullable|integer',
            'direccion' => 'nullable|string',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'referencia_nombre' => 'nullable|string|max:100',
            'referencia_celular' => 'nullable|string|max:20',
            'foto' => 'nullable|image|max:2048',
        ]);

        $user = $request->user();
        $miembro = $user->miembro;

        if (!$miembro) {
             return response()->json(['success' => false, 'message' => 'Perfil de miembro no encontrado'], 404);
        }

        $data = $request->only([
            'celular',
            'direccion',
            'latitud',
            'longitud',
            'referencia_nombre',
            'referencia_celular',
        ]);

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('fotos', 'public');
            $data['foto'] = $path;
        }

        $miembro->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente',
            'data' => $this->formatUserMiembro($user->fresh(['miembro.seccion', 'miembro.categoria', 'roles'])),
        ]);
    }

    /**
     * Formatea los datos combinados de User y Miembro
     */
    private function formatUserMiembro(\App\Models\User $user): array
    {
        $miembro = $user->miembro;

        if (!$miembro) {
            // Fallback por si es un usuario sin perfil de miembro (admin puro?)
            return [
                'id' => $user->id,
                'usuario' => $user->username,
                'es_super_admin' => $user->hasRole(\App\Models\Rol::SUPER_ADMIN),
                'nombre_completo' => $user->username,
            ];
        }

        // Obtener rol principal (el de mayor nivel)
        $rolPrincipal = $user->roles->sortByDesc('nivel')->first();

        return [
            'id' => $miembro->id,
            'user_id' => $user->id,
            'nombres' => $miembro->nombres,
            'apellidos' => $miembro->apellidos,
            'nombre_completo' => $miembro->nombre_completo,
            'iniciales' => $miembro->iniciales,
            'ci' => $miembro->ci_completo,
            'celular' => $miembro->celular,
            'usuario' => $user->username,
            'foto' => $miembro->foto,
            'direccion' => $miembro->direccion,
            'latitud' => $miembro->latitud,
            'longitud' => $miembro->longitud,
            'seccion' => $miembro->seccion ? [
                'id' => $miembro->seccion->id,
                'nombre' => $miembro->seccion->nombre,
                'icono' => $miembro->seccion->icono,
                'color' => $miembro->seccion->color,
            ] : null,
            'categoria' => $miembro->categoria ? [
                'id' => $miembro->categoria->id,
                'codigo' => $miembro->categoria->codigo,
                'nombre' => $miembro->categoria->nombre,
            ] : null,
            'rol' => $rolPrincipal ? [
                'id' => $rolPrincipal->id,
                'nombre' => $rolPrincipal->nombre,
                'slug' => $rolPrincipal->slug,
            ] : null,
            'roles' => $user->roles->map(function($r) {
                return ['id' => $r->id, 'nombre' => $r->nombre, 'slug' => $r->slug];
            }),
            'es_super_admin' => $miembro->esSuperAdmin(),
            'es_director' => $miembro->esDirector(),
            'es_jefe_seccion' => $miembro->esJefeSeccion(),
        ];
    }
}
