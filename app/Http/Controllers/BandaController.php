<?php

namespace App\Http\Controllers;

use App\Models\Banda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BandaController extends Controller
{
    /**
     * Obtiene la información visual de una banda para personalizar el login
     * Cached for 1 hour to reduce DB hits on login page
     */
    public function getBranding($slug)
    {
        $cleanSlug = trim(strtolower($slug));

        // Cache branding por 1 hora - rara vez cambia
        $result = Cache::remember("branding.{$cleanSlug}", 3600, function() use ($cleanSlug) {
            $banda = Banda::where('slug', $cleanSlug)
                ->where('estado', true)
                ->first();

            if (!$banda) {
                return null;
            }

            return [
                'id_banda' => $banda->id_banda,
                'nombre' => $banda->nombre,
                'slug' => $banda->slug,
                'logo' => $banda->logo ? '/storage/' . $banda->logo : null,
                'color_primario' => $banda->color_primario,
                'color_secundario' => $banda->color_secundario,
            ];
        });

        if (!$result) {
            return response()->json(['message' => 'Banda no encontrada'], 404);
        }

        return response()->json($result);
    }
}
