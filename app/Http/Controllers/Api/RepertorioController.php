<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Genero;
use App\Models\Tema;
use App\Models\Partitura;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RepertorioController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    // GÉNEROS
    // ═══════════════════════════════════════════════════════════

    public function generos(Request $request): JsonResponse
    {
        $generos = Genero::activos()
            ->ordenados()
            ->withCount(['temas' => function ($q) {
                $q->where('activo', true);
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $generos,
        ]);
    }

    public function crearGenero(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:generos,nombre',
            'descripcion' => 'nullable|string',
            'icono' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
        ]);

        $genero = Genero::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Género creado correctamente',
            'data' => $genero,
        ], 201);
    }

    public function actualizarGenero(Request $request, Genero $genero): JsonResponse
    {
        $request->validate([
            'nombre' => 'sometimes|string|max:100|unique:generos,nombre,' . $genero->id,
            'descripcion' => 'nullable|string',
            'icono' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'orden' => 'nullable|integer',
            'activo' => 'nullable|boolean',
        ]);

        $genero->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Género actualizado correctamente',
            'data' => $genero,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // TEMAS
    // ═══════════════════════════════════════════════════════════

    public function temas(Genero $genero): JsonResponse
    {
        $temas = $genero->temas()
            ->activos()
            ->ordenados()
            ->withCount(['partituras' => function ($q) {
                $q->where('activo', true);
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'genero' => $genero,
                'temas' => $temas,
            ],
        ]);
    }

    public function crearTema(Request $request, Genero $genero): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'compositor' => 'nullable|string|max:200',
            'duracion_segundos' => 'nullable|integer|min:0',
        ]);

        $tema = $genero->temas()->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tema creado correctamente',
            'data' => $tema,
        ], 201);
    }

    public function actualizarTema(Request $request, Tema $tema): JsonResponse
    {
        $request->validate([
            'nombre' => 'sometimes|string|max:200',
            'descripcion' => 'nullable|string',
            'compositor' => 'nullable|string|max:200',
            'duracion_segundos' => 'nullable|integer|min:0',
            'orden' => 'nullable|integer',
            'activo' => 'nullable|boolean',
        ]);

        $tema->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tema actualizado correctamente',
            'data' => $tema,
        ]);
    }

    public function verTema(Tema $tema): JsonResponse
    {
        $tema->load(['genero', 'partituras.seccion']);

        return response()->json([
            'success' => true,
            'data' => $tema,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // PARTITURAS
    // ═══════════════════════════════════════════════════════════

    public function partituras(Request $request, Tema $tema): JsonResponse
    {
        $user = $request->user();
        $miembro = $user->miembro;

        $query = $tema->partituras()->activas()->with('seccion');

        // Lógica de permisos: Super Admin y Director ven todo.
        // Otros solo ven su sección.
        $esSuperAdmin = $user->hasRole('super_admin'); // Usando string directo por seguridad si la constante no está importada
        $esDirector = $miembro ? $miembro->esDirector() : false;

        if (!$esSuperAdmin && !$esDirector) {
            if ($miembro) {
                 $query->where('seccion_id', $miembro->seccion_id);
            } else {
                 // Usuario sin perfil (y no es admin) -> no ve nada
                 return response()->json(['success' => true, 'data' => ['tema' => $tema->load('genero'), 'partituras' => []]]);
            }
        }

        $partituras = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'tema' => $tema->load('genero'),
                'partituras' => $partituras,
            ],
        ]);
    }

    public function subirPartitura(Request $request, Tema $tema): JsonResponse
    {
        $request->validate([
            'seccion_id' => 'required|exists:secciones,id',
            'archivo' => 'required|file|max:10240', // 10MB max
            'titulo' => 'nullable|string|max:200',
            'notas' => 'nullable|string',
        ]);

        $archivo = $request->file('archivo');
        $extension = strtolower($archivo->getClientOriginalExtension());

        // Determinar tipo de archivo
        $tipoArchivo = match(true) {
            $extension === 'pdf' => Partitura::TIPO_PDF,
            in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']) => Partitura::TIPO_IMAGEN,
            in_array($extension, ['mp3', 'wav', 'ogg', 'm4a']) => Partitura::TIPO_AUDIO,
            default => throw new \Exception('Tipo de archivo no soportado'),
        };

        // Guardar archivo
        $nombreArchivo = Str::slug($tema->nombre) . '-seccion-' . $request->seccion_id . '-' . time() . '.' . $extension;
        $ruta = $archivo->storeAs('partituras/' . $tema->genero_id, $nombreArchivo, 'public');

        $partitura = Partitura::create([
            'tema_id' => $tema->id,
            'seccion_id' => $request->seccion_id,
            'titulo' => $request->titulo ?? $archivo->getClientOriginalName(),
            'tipo_archivo' => $tipoArchivo,
            'archivo' => $ruta,
            'archivo_original' => $archivo->getClientOriginalName(),
            'tamaño_bytes' => $archivo->getSize(),
            'notas' => $request->notas,
            'subido_por' => $request->user()->miembro ? $request->user()->miembro->id : null, // Asumiendo que subido_por es FK a miembros
        ]);

        $partitura->load('seccion');

        return response()->json([
            'success' => true,
            'message' => 'Partitura subida correctamente',
            'data' => $partitura,
        ], 201);
    }

    public function eliminarPartitura(Partitura $partitura): JsonResponse
    {
        // Eliminar archivo físico
        if (Storage::disk('public')->exists($partitura->archivo)) {
            Storage::disk('public')->delete($partitura->archivo);
        }

        $partitura->delete();

        return response()->json([
            'success' => true,
            'message' => 'Partitura eliminada correctamente',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // PARTITURAS DE MI SECCIÓN
    // ═══════════════════════════════════════════════════════════

    public function misPartituras(Request $request): JsonResponse
    {
        $miembro = $request->user()->miembro;

        if (!$miembro) {
             return response()->json(['success' => true, 'data' => []]);
        }

        $generos = Genero::activos()
            ->ordenados()
            ->with(['temas' => function ($q) use ($miembro) {
                $q->activos()
                    ->ordenados()
                    ->whereHas('partituras', function ($qp) use ($miembro) {
                        $qp->where('seccion_id', $miembro->seccion_id)
                            ->where('activo', true);
                    })
                    ->with(['partituras' => function ($qp) use ($miembro) {
                        $qp->where('seccion_id', $miembro->seccion_id)
                            ->where('activo', true);
                    }]);
            }])
            ->get()
            ->filter(function ($genero) {
                return $genero->temas->isNotEmpty();
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $generos,
        ]);
    }
}
