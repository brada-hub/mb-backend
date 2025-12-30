<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMiembroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add admin check here later
    }

    public function rules(): array
    {
        return [
            // Datos Personales
            'nombres' => 'required|string|max:50',
            'apellidos' => 'required|string|max:50',
            'ci' => 'required|string|unique:miembros,ci',
            'celular' => 'required|integer',
            'fecha' => 'nullable|date',

            // Mapa de Lealtad
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'direccion' => 'nullable|string',

            // Vínculos Operativos
            'id_categoria' => 'required|exists:categorias,id_categoria',
            'id_seccion' => 'required|exists:secciones,id_seccion',
            'id_rol' => 'required|exists:roles,id_rol',

            // Contacto de Emergencia (Optional but validated if present)
            'contacto_nombre' => 'nullable|string|max:100',
            'contacto_parentesco' => 'nullable|string|max:50',
            'contacto_celular' => 'nullable|integer',

            // User auto-creation
            'create_user' => 'boolean',
            'username' => 'nullable|string|unique:users,user|required_if:create_user,true',
            'password' => 'nullable|string|min:6|required_if:create_user,true'
        ];
    }
}
