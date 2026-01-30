<?php

namespace App\Http\Controllers;

use App\Models\Banda;
use Illuminate\Http\Request;

class BandaController extends Controller
{
    /**
     * Obtiene la información visual de una banda para personalizar el login
     */
    public function getBranding($slug)
    {
        // Limpiamos el slug por si llegan espacios o caracteres extraños
        $cleanSlug = trim(strtolower($slug));

        $banda = Banda::where('slug', $cleanSlug)
            ->where('estado', true)
            ->first();

        if (!$banda) {
            return response()->json(['message' => 'Banda no encontrada'], 404);
        }

        return response()->json([
            'id_banda' => $banda->id_banda,
            'nombre' => $banda->nombre,
            'slug' => $banda->slug,
            'logo' => $banda->logo ? '/storage/' . $banda->logo : null,
            'color_primario' => $banda->color_primario,
            'color_secundario' => $banda->color_secundario,
        ]);
    }
}
