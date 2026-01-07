<?php

namespace App\Http\Controllers;

use App\Models\Mix;
use App\Models\DetalleMix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MixController extends Controller
{
    /**
     * Lista todos los mixes.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Mix::with('audio')->withCount('temas');

        // Determinar si es admin/director/jefe buscando el rol en la relación miembro
        $isAdmin = false;
        if ($user && $user->miembro && $user->miembro->rol) {
            $roleName = $user->miembro->rol->rol;
            $isAdmin = $roleName === 'ADMIN' || $roleName === 'DIRECTOR' || (is_string($roleName) && str_contains($roleName, 'JEFE'));
        }

        // Si no es admin/director/jefe, y existe la columna 'activo', solo ver los activos
        if (!$isAdmin && \Illuminate\Support\Facades\Schema::hasColumn('mixes', 'activo')) {
            $query->where('activo', true);
        }

        return $query->get();
    }

    /**
     * Obtiene un mix con sus temas ordenados.
     */
    public function show($id)
    {
        return Mix::with(['audio', 'temas.audio', 'temas.genero', 'temas.recursos.archivos', 'temas.recursos.voz'])->findOrFail($id);
    }

    /**
     * Crea un nuevo mix.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'nullable|boolean',
            'temas' => 'nullable|array', // Array de IDs de temas
            'temas.*' => 'exists:temas,id_tema',
            'audio_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a|max:20480'
        ]);

        try {
            DB::beginTransaction();

            $mix = Mix::create([
                'nombre' => mb_strtoupper($request->nombre, 'UTF-8'),
                'activo' => $request->get('activo', true)
            ]);

            if ($request->has('temas')) {
                foreach ($request->temas as $index => $idTema) {
                    DetalleMix::create([
                        'id_mix' => $mix->id_mix,
                        'id_tema' => $idTema,
                        'orden' => $index + 1
                    ]);
                }
            }

            if ($request->hasFile('audio_file')) {
                $path = $request->file('audio_file')->store('audios', 'public');
                $fullUrl = asset('storage/' . $path);
                $mix->audio()->create(['url_audio' => $fullUrl]);
            }

            DB::commit();
            return response()->json($mix->load(['temas', 'audio']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualiza un mix (nombre y reordenamiento de temas).
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'nullable|boolean',
            'temas' => 'nullable|array',
            'temas.*' => 'exists:temas,id_tema',
            'audio_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a|max:20480'
        ]);

        try {
            DB::beginTransaction();

            $mix = Mix::findOrFail($id);

            $data = $request->only('nombre', 'activo');
            if (isset($data['nombre'])) {
                $data['nombre'] = mb_strtoupper($data['nombre'], 'UTF-8');
            }

            $mix->update($data);

            // Reemplazar temas (forma sencilla: borrar y crear)
            if ($request->has('temas')) {
                DetalleMix::where('id_mix', $id)->delete();
                foreach ($request->temas as $index => $idTema) {
                    DetalleMix::create([
                        'id_mix' => $id,
                        'id_tema' => $idTema,
                        'orden' => $index + 1
                    ]);
                }
            }

            if ($request->hasFile('audio_file')) {
                if ($mix->audio) {
                    $mix->audio()->delete();
                }
                $path = $request->file('audio_file')->store('audios', 'public');
                $fullUrl = asset('storage/' . $path);
                $mix->audio()->create(['url_audio' => $fullUrl]);
            }

            DB::commit();
            return response()->json($mix->load(['temas', 'audio']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Elimina un mix.
     */
    public function destroy($id)
    {
        Mix::findOrFail($id)->delete();
        return response()->json(['message' => 'Mix eliminado correctamente']);
    }
}
