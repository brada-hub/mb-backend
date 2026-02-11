<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Uniforme;
use App\Models\UniformeItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UniformeController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $bandaId = $user->id_banda;

        if (!$bandaId) {
            return response()->json(['message' => 'Usuario no pertenece a una banda'], 403);
        }

        $uniformes = Uniforme::where('banda_id', $bandaId)
            ->with('items')
            ->get();

        return response()->json($uniformes);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->id_banda) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'nombre' => 'required|string|max:255',
            'items' => 'required|array',
            'items.*.tipo' => 'required|string',
            'items.*.color' => 'required|string', // hex
        ]);

        try {
            DB::beginTransaction();

            $uniforme = Uniforme::create([
                'banda_id' => $user->id_banda,
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion
            ]);

            foreach ($request->items as $item) {
                UniformeItem::create([
                    'uniforme_id' => $uniforme->id,
                    'tipo' => $item['tipo'],
                    'color' => $item['color'],
                    'detalle' => $item['detalle'] ?? null
                ]);
            }

            DB::commit();
            return response()->json($uniforme->load('items'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear uniforme', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $uniforme = Uniforme::where('id', $id)->where('banda_id', $user->id_banda)->firstOrFail();

        try {
            DB::beginTransaction();

            $uniforme->update([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion
            ]);

            // Reemplazo completo de items por simplicidad
            if ($request->has('items')) {
                $uniforme->items()->delete();
                foreach ($request->items as $item) {
                    UniformeItem::create([
                        'uniforme_id' => $uniforme->id,
                        'tipo' => $item['tipo'],
                        'color' => $item['color'],
                        'detalle' => $item['detalle'] ?? null
                    ]);
                }
            }

            DB::commit();
            return response()->json($uniforme->load('items'));

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar uniforme', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $uniforme = Uniforme::where('id', $id)->where('banda_id', $user->id_banda)->firstOrFail();
        $uniforme->delete();
        return response()->json(['message' => 'Uniforme eliminado']);
    }
}
