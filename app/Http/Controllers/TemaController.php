<?php

namespace App\Http\Controllers;

use App\Models\Tema;
use Illuminate\Http\Request;

class TemaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isMiembro = $user && $user->miembro && $user->miembro->rol && $user->miembro->rol->rol === 'MIEMBRO';
        $instrumentoId = $isMiembro ? $user->miembro->id_instrumento : null;

        $query = Tema::with(['genero', 'videos', 'audio'])
            ->withCount([
                'recursos as recursos_count',

                'recursos as partituras_count' => function($q) use ($isMiembro, $instrumentoId) {
                    if ($isMiembro) {
                        $q->where('id_instrumento', $instrumentoId);
                    }
                    $q->whereHas('archivos', function($aq) {
                        $aq->whereIn('tipo', ['pdf', 'imagen']);
                    });
                },
                'recursos as guias_count' => function($q) use ($isMiembro, $instrumentoId) {
                    if ($isMiembro) {
                        $q->where('id_instrumento', $instrumentoId);
                    }
                    $q->whereHas('archivos', function($aq) {
                        $aq->where('tipo', 'audio');
                    });
                }
            ]);

        if ($request->has('id_genero')) {
            $query->where('id_genero', $request->id_genero);
        }

        // Load recursos for all users
        if ($isMiembro && $instrumentoId) {
            // For members: filter by their instrument
            $query->with(['recursos' => function($q) use ($instrumentoId) {
                $q->where('id_instrumento', $instrumentoId)
                  ->with(['archivos', 'voz']);
            }]);
        } else {
            // For admins/directors: load all recursos
            $query->with(['recursos' => function($q) {
                $q->with(['archivos', 'voz']);
            }]);
        }

        return $query->get();
    }

    public function show($id)
    {
        return Tema::with(['genero', 'videos', 'audio', 'recursos.archivos', 'recursos.voz', 'recursos.instrumento'])
            ->findOrFail($id);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_genero' => 'required|exists:generos,id_genero',
            'nombre_tema' => 'required|string|max:255',
            'url_video' => 'nullable|string',
            'audio_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a|max:20480' // Max 20MB
        ]);

        $tema = Tema::create([
            'id_genero' => $validated['id_genero'],
            'nombre_tema' => mb_strtoupper($validated['nombre_tema'], 'UTF-8')
        ]);

        if (!empty($validated['url_video'])) {
            $tema->videos()->create([
                'url_video' => $validated['url_video'],
                'titulo' => 'Video Referencial'
            ]);
        }

        if ($request->hasFile('audio_file')) {
            $path = $request->file('audio_file')->store('audios', 'public');
            $fullUrl = asset('storage/' . $path);

            $tema->audio()->create([
                'url_audio' => $fullUrl,
                'tipo_entidad' => 'TEMA' // Actually automatic via morph, but explicitness doesn't hurt or just let Eloquent handle it
            ]);
        }

        return response()->json($tema->load(['genero', 'videos', 'audio']), 201);
    }

    public function update(Request $request, $id)
    {
        $tema = Tema::findOrFail($id);
        $validated = $request->validate([
            'id_genero' => 'nullable|exists:generos,id_genero',
            'nombre_tema' => 'nullable|string|max:255',
            'url_video' => 'nullable|string',
            'audio_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a|max:20480'
        ]);

        if (isset($validated['id_genero'])) {
            $tema->id_genero = $validated['id_genero'];
        }
        if (isset($validated['nombre_tema'])) {
            $tema->nombre_tema = mb_strtoupper($validated['nombre_tema'], 'UTF-8');
        }

        $tema->save();

        if (array_key_exists('url_video', $validated)) {
            if (empty($validated['url_video'])) {
                $tema->videos()->delete();
            } else {
                $video = $tema->videos()->first();
                if ($video) {
                    $video->update(['url_video' => $validated['url_video']]);
                } else {
                    $tema->videos()->create([
                        'url_video' => $validated['url_video'],
                        'titulo' => 'Video Referencial'
                    ]);
                }
            }
        }

        if ($request->hasFile('audio_file')) {
            // Remove old audio if exists
            if ($tema->audio) {
                // Should delete file from storage too ideally, skipping for brevity/safety unless explicitly requested
                $tema->audio()->delete();
            }

            $path = $request->file('audio_file')->store('audios', 'public');
            $fullUrl = asset('storage/' . $path);

            $tema->audio()->create([
                'url_audio' => $fullUrl
            ]);
        }

        return response()->json($tema->load(['genero', 'videos', 'audio']));
    }

    public function destroy($id)
    {
        $tema = Tema::findOrFail($id);
        if ($tema->recursos()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar el tema porque tiene recursos asociados.'], 422);
        }
        $tema->delete();
        return response()->json(['message' => 'Tema eliminado correctamente']);
    }
}
