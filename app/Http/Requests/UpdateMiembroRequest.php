<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMiembroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Permitir actualizaciones (agregar permisos específicos después)
    }

    /**
     * Preparar los datos antes de la validación.
     * Convierte todos los campos de texto a MAYÚSCULAS para homogeneidad.
     */
    protected function prepareForValidation(): void
    {
        $dataToMerge = [];

        // Solo convertir campos que existen en la solicitud
        if ($this->has('nombres')) {
            $dataToMerge['nombres'] = mb_strtoupper(trim($this->nombres), 'UTF-8');
        }
        if ($this->has('apellidos')) {
            $dataToMerge['apellidos'] = mb_strtoupper(trim($this->apellidos), 'UTF-8');
        }
        if ($this->has('ci')) {
            $dataToMerge['ci'] = mb_strtoupper(trim($this->ci), 'UTF-8');
        }
        if ($this->has('direccion')) {
            $dataToMerge['direccion'] = mb_strtoupper(trim($this->direccion), 'UTF-8');
        }
        if ($this->has('contacto_nombre')) {
            $dataToMerge['contacto_nombre'] = $this->contacto_nombre ? mb_strtoupper(trim($this->contacto_nombre), 'UTF-8') : null;
        }
        if ($this->has('contacto_parentesco')) {
            $dataToMerge['contacto_parentesco'] = $this->contacto_parentesco ? mb_strtoupper(trim($this->contacto_parentesco), 'UTF-8') : null;
        }

        if (!empty($dataToMerge)) {
            $this->merge($dataToMerge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $miembroId = $this->route('miembro');

        return [
            // ==========================================
            // DATOS PERSONALES - Validaciones Bolivia
            // ==========================================

            // NOMBRES: Solo letras (incluyendo ñ, acentos), 2-50 caracteres
            'nombres' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ\s]+$/'
            ],

            // APELLIDOS: Solo letras (incluyendo ñ, acentos), 2-50 caracteres
            'apellidos' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ\s]+$/'
            ],

            // CI BOLIVIA: 5-10 dígitos + opcional guion con UNA letra mayúscula
            'ci' => [
                'sometimes',
                'required',
                'string',
                'regex:/^[0-9]{5,10}(-[A-Z])?$/',
                Rule::unique('miembros', 'ci')->ignore($miembroId, 'id_miembro')
            ],

            // CELULAR BOLIVIA: Exactamente 8 dígitos, empieza con 6 o 7
            'celular' => [
                'sometimes',
                'required',
                'string',
                'size:8',
                'regex:/^[67][0-9]{7}$/'
            ],

            // FECHA NACIMIENTO
            'fecha' => 'nullable|date|before:today',

            // ==========================================
            // GEOLOCALIZACIÓN Y DIRECCIÓN
            // ==========================================
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'direccion' => [
                'sometimes',
                'required',
                'string',
                'min:10',
                'max:200',
                'regex:/^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ0-9\s.,#\-\/]+$/'
            ],

            // ==========================================
            // VÍNCULOS OPERATIVOS
            // ==========================================
            'id_categoria' => 'sometimes|required|integer|exists:categorias,id_categoria',
            'id_seccion' => 'sometimes|required|integer|exists:secciones,id_seccion',
            'id_rol' => 'sometimes|required|integer|exists:roles,id_rol',

            // ==========================================
            // VERSIÓN DE PERFIL (para control de actualizaciones)
            // ==========================================
            'version_perfil' => 'sometimes|integer|min:0',

            // ==========================================
            // CONTACTO DE EMERGENCIA
            // ==========================================
            'has_emergency_contact' => 'sometimes|boolean',

            'contacto_nombre' => [
                'nullable',
                'required_if:has_emergency_contact,true',
                'string',
                'min:3',
                'max:100',
                'regex:/^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ\s]+$/'
            ],

            'contacto_parentesco' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ\s]*$/'
            ],

            'contacto_celular' => [
                'nullable',
                'required_if:has_emergency_contact,true',
                'string',
                'size:8',
                'regex:/^[67][0-9]{7}$/',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $num = (int) $value;
                        if ($num < 60000000 || $num > 79999999) {
                            $fail('El celular de emergencia debe estar en el rango boliviano.');
                        }
                    }
                }
            ],
        ];
    }

    /**
     * Mensajes de error personalizados en español
     */
    public function messages(): array
    {
        return [
            // Nombres
            'nombres.min' => 'El nombre debe tener al menos 2 caracteres.',
            'nombres.max' => 'El nombre no puede exceder 50 caracteres.',
            'nombres.regex' => 'El nombre solo puede contener letras.',

            // Apellidos
            'apellidos.min' => 'Los apellidos deben tener al menos 2 caracteres.',
            'apellidos.max' => 'Los apellidos no pueden exceder 50 caracteres.',
            'apellidos.regex' => 'Los apellidos solo pueden contener letras.',

            // CI
            'ci.regex' => 'Formato de CI inválido. Use: 1234567 o 1234567-L.',
            'ci.unique' => 'Este CI ya está registrado en el sistema.',

            // Celular
            'celular.size' => 'El celular debe tener exactamente 8 dígitos.',
            'celular.regex' => 'El celular debe comenzar con 6 o 7 (Bolivia).',

            // Dirección
            'direccion.min' => 'La dirección debe tener al menos 10 caracteres.',
            'direccion.max' => 'La dirección no puede exceder 200 caracteres.',
            'direccion.regex' => 'La dirección contiene caracteres no permitidos.',

            // Fecha
            'fecha.before' => 'La fecha de nacimiento debe ser anterior a hoy.',

            // Catálogos
            'id_categoria.exists' => 'La categoría seleccionada no existe.',
            'id_seccion.exists' => 'La sección seleccionada no existe.',
            'id_rol.exists' => 'El rol seleccionado no existe.',

            // Contacto de emergencia
            'contacto_nombre.required_if' => 'El nombre del contacto es requerido.',
            'contacto_nombre.regex' => 'El nombre del contacto solo puede contener letras.',
            'contacto_celular.required_if' => 'El celular de emergencia es requerido.',
            'contacto_celular.size' => 'El celular de emergencia debe tener 8 dígitos.',
            'contacto_celular.regex' => 'El celular de emergencia debe comenzar con 6 o 7.',
        ];
    }
}
