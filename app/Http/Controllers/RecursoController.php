<?php

namespace App\Http\Controllers;

use App\Models\Recurso;
use App\Models\Archivo;
use App\Models\Video;
use App\Models\Tema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class RecursoController extends Controller
{
    /**
     * Lista recursos con sus archivos, instrumentos y voces.
     */
    public function index(Request $request)
    {
        // Eager load everything needed for the new UI
        $query = Recurso::with(['tema.genero', 'instrumento.seccion', 'voz', 'archivos']);

        if ($request->has('id_tema')) {
            $query->where('id_tema', $request->id_tema);
        }
        // Filter by instrument if provided, or section via instrument relation logic if needed
        if ($request->has('id_instrumento')) {
            $query->where('id_instrumento', $request->id_instrumento);
        }

        // If frontend still sends id_seccion, we might want to filter resources belonging to that section
        if ($request->has('id_seccion')) {
            $query->whereHas('instrumento', function($q) use ($request) {
                $q->where('id_seccion', $request->id_seccion);
            });
        }

        return $query->get();
    }

    /**
     * Sube y crea un nuevo recurso (Partitura/Audio) con soporte para múltiples archivos.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_tema' => 'required', // Puede ser ID o 'NEW'
            'nuevo_tema_nombre' => 'required_if:id_tema,NEW|nullable|string|max:255',
            'id_genero' => 'required_if:id_tema,NEW|nullable|exists:generos,id_genero',
            'id_instrumento' => 'required|exists:instrumentos,id_instrumento',
            'id_voz' => 'required|exists:voces_instrumentales,id_voz',
            // Support multiple files
            'new_archivos' => 'nullable|array',
            'new_archivos.*' => 'file|max:20480',
            'archivos' => 'nullable|array',
            'archivos.*' => 'file|max:20480',
            'archivo' => 'nullable|file|max:20480',
            'audio_guia' => 'nullable|file|max:20480',
            'video_url_opcional' => 'nullable|url',
        ]);

        try {
            DB::beginTransaction();

            $idTema = $request->id_tema;

            // Lógica para crear tema si es nuevo
            if ($idTema === 'NEW') {
                $nuevoTema = Tema::create([
                    'id_genero' => $request->id_genero,
                    'nombre_tema' => mb_strtoupper($request->nuevo_tema_nombre, 'UTF-8')
                ]);
                $idTema = $nuevoTema->id_tema;
            }

            // Create Recurso Parent
            $recurso = Recurso::create([
                'id_tema' => $idTema,
                'id_instrumento' => $request->id_instrumento,
                'id_voz' => $request->id_voz
            ]);

            // Handle Files (Archivos)
            $filesToProcess = [];
            if ($request->hasFile('new_archivos')) {
                $filesToProcess = $request->file('new_archivos');
            } elseif ($request->hasFile('archivos')) {
                $filesToProcess = $request->file('archivos');
            } elseif ($request->hasFile('archivo')) {
                // Backward compatibility
                $filesToProcess = [$request->file('archivo')];
            }

            foreach ($filesToProcess as $index => $file) {
                $path = $file->store('recursos', 'public');
                $tipo = $file->getClientOriginalExtension() === 'pdf' ? 'pdf' : 'imagen';

                Archivo::create([
                    'id_recurso' => $recurso->id_recurso,
                    'url_archivo' => Storage::url($path),
                    'tipo' => $tipo,
                    'nombre_original' => $file->getClientOriginalName(),
                    'orden' => $index + 1
                ]);
            }

            // Handle Audio Guide (as another Archivo with type 'audio')
            if ($request->hasFile('audio_guia')) {
                $audioPath = $request->file('audio_guia')->store('guias', 'public');
                Archivo::create([
                    'id_recurso' => $recurso->id_recurso,
                    'url_archivo' => Storage::url($audioPath),
                    'tipo' => 'audio',
                    'nombre_original' => 'Audio Guía',
                    'orden' => 99
                ]);
            }

            // Handle Video (Now in separate table linked to Tema? Or linked to Recurso?)
            // User diagram says Video -> Tema (1:1).
            // But if user sends it here, we might want to attach it to the theme.
            if ($request->video_url_opcional) {
                // Check if video exists for theme? or just add new one.
                Video::create([
                    'id_tema' => $idTema,
                    'url_video' => $request->video_url_opcional,
                    'titulo' => 'Referencia'
                ]);
            }

            DB::commit();

            return response()->json($recurso->load(['tema.genero', 'instrumento.seccion', 'voz', 'archivos']), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al guardar recurso: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Actualiza un recurso existente.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'id_tema' => 'required',
            'id_instrumento' => 'required|exists:instrumentos,id_instrumento',
            'id_voz' => 'required|exists:voces_instrumentales,id_voz',
            'existing_files_order' => 'nullable|string', // JSON array of IDs
            'new_archivos' => 'nullable|array',
            'new_archivos.*' => 'file|max:20480',
            'audio_guia' => 'nullable|file|max:20480',
            'delete_audio' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();
            $recurso = Recurso::findOrFail($id);

            // 1. Update Basic Data
            $recurso->update([
                'id_tema' => $request->id_tema,
                'id_instrumento' => $request->id_instrumento,
                'id_voz' => $request->id_voz
            ]);

            // 2. Handle Existing Files (Reorder / Delete)
            $existingOrder = json_decode($request->existing_files_order, true) ?: [];

            // Get all current non-audio files
            $currentFiles = $recurso->archivos()->where('tipo', '!=', 'audio')->get();

            foreach ($currentFiles as $file) {
                if (!in_array($file->id_archivo, $existingOrder)) {
                    // File was removed in UI
                    $path = str_replace('/storage/', '', $file->url_archivo);
                    Storage::disk('public')->delete($path);
                    $file->delete();
                } else {
                    // Update Order
                    $newPos = array_search($file->id_archivo, $existingOrder);
                    $file->update(['orden' => $newPos + 1]);
                }
            }

            $lastOrder = count($existingOrder);

            // 3. Handle New Files
            if ($request->hasFile('new_archivos')) {
                foreach ($request->file('new_archivos') as $file) {
                    $path = $file->store('recursos', 'public');
                    $tipo = $file->getClientOriginalExtension() === 'pdf' ? 'pdf' : 'imagen';
                    $lastOrder++;

                    Archivo::create([
                        'id_recurso' => $recurso->id_recurso,
                        'url_archivo' => Storage::url($path),
                        'tipo' => $tipo,
                        'nombre_original' => $file->getClientOriginalName(),
                        'orden' => $lastOrder
                    ]);
                }
            }

            // 4. Handle Audio
            if ($request->hasFile('audio_guia')) {
                // Delete existing audio first
                $existingAudio = $recurso->archivos()->where('tipo', 'audio')->first();
                if ($existingAudio) {
                    $path = str_replace('/storage/', '', $existingAudio->url_archivo);
                    Storage::disk('public')->delete($path);
                    $existingAudio->delete();
                }

                $audioPath = $request->file('audio_guia')->store('guias', 'public');
                Archivo::create([
                    'id_recurso' => $recurso->id_recurso,
                    'url_archivo' => Storage::url($audioPath),
                    'tipo' => 'audio',
                    'nombre_original' => 'Audio Guía',
                    'orden' => 99
                ]);
            } elseif ($request->delete_audio === 'true') {
                $existingAudio = $recurso->archivos()->where('tipo', 'audio')->first();
                if ($existingAudio) {
                    $path = str_replace('/storage/', '', $existingAudio->url_archivo);
                    Storage::disk('public')->delete($path);
                    $existingAudio->delete();
                }
            }

            DB::commit();
            return response()->json($recurso->load(['archivos']));

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar recurso: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Elimina un recurso y sus archivos hijos.
     */
    public function destroy($id)
    {
        $recurso = Recurso::with('archivos')->findOrFail($id);

        // Delete physical files
        foreach ($recurso->archivos as $archivo) {
            $path = str_replace('/storage/', '', $archivo->url_archivo);
            Storage::disk('public')->delete($path);
        }

        // Delete DB record (Cascades delete to archivos table, but we deleted files above)
        $recurso->delete();

        return response()->json(['message' => 'Recurso eliminado correctamente']);
    }
}
