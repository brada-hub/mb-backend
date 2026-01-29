namespace App\Http\Controllers;

use App\Models\Formacion;
use App\Models\DetalleFormacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormacionController extends Controller
{
    /**
     * Listar formaciones de la banda.
     */
    public function index()
    {
        $formaciones = Formacion::with(['miembros.instrumento'])->withCount('miembros')->get();

        $formaciones->map(function($f) {
            // Generar un resumen simple: "Trp: 4, Sax: 3..."
            $resumen = $f->miembros->groupBy(function($m) {
                return $m->instrumento->instrumento ?? 'OTRO';
            })->map(function($group) {
                return $group->count();
            });

            $f->resumen_instrumentos = $resumen;
            unset($f->miembros); // No enviar toda la data de miembros en el index para ahorrar ancho de banda
            return $f;
        });

        return $formaciones;
    }

    /**
     * Crear una nueva formación.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'id_miembros' => 'required|array',
            'id_miembros.*' => 'exists:miembros,id_miembro'
        ]);

        return DB::transaction(function () use ($request) {
            $formacion = Formacion::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'id_banda' => auth()->user()->id_banda
            ]);

            foreach ($request->id_miembros as $id_miembro) {
                DetalleFormacion::create([
                    'id_formacion' => $formacion->id_formacion,
                    'id_miembro' => $id_miembro
                ]);
            }

            return $formacion->load('miembros.instrumento');
        });
    }

    /**
     * Mostrar detalle de una formación.
     */
    public function show($id)
    {
        return Formacion::with(['miembros.instrumento', 'miembros.seccion'])->findOrFail($id);
    }

    /**
     * Actualizar una formación.
     */
    public function update(Request $request, $id)
    {
        $formacion = Formacion::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'id_miembros' => 'required|array',
            'id_miembros.*' => 'exists:miembros,id_miembro'
        ]);

        return DB::transaction(function () use ($request, $formacion) {
            $formacion->update([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion
            ]);

            // Sincronizar miembros
            DetalleFormacion::where('id_formacion', $formacion->id_formacion)->delete();
            foreach ($request->id_miembros as $id_miembro) {
                DetalleFormacion::create([
                    'id_formacion' => $formacion->id_formacion,
                    'id_miembro' => $id_miembro
                ]);
            }

            return $formacion->load('miembros.instrumento');
        });
    }

    /**
     * Eliminar una formación.
     */
    public function destroy($id)
    {
        $formacion = Formacion::findOrFail($id);
        $formacion->delete();
        return response()->json(['message' => 'Formación eliminada correctamente']);
    }

    /**
     * Activar/Desactivar formación.
     */
    public function toggleActivo($id)
    {
        $formacion = Formacion::findOrFail($id);
        $formacion->activo = !$formacion->activo;
        $formacion->save();

        return response()->json([
            'message' => $formacion->activo ? 'Formación activada' : 'Formación desactivada',
            'activo' => $formacion->activo
        ]);
    }
}
