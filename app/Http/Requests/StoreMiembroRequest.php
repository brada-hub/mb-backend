<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMiembroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add admin check here later
    }

    /**
     * Preparar los datos antes de la validación.
     * Convierte todos los campos de texto a MAYÚSCULAS para homogeneidad.
     */
    protected function prepareForValidation(): void
    {
        $dataToMerge = [];

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
            $dataToMerge['contacto_nombre'] = mb_strtoupper(trim($this->contacto_nombre), 'UTF-8');
        }
        if ($this->has('contacto_parentesco')) {
            $dataToMerge['contacto_parentesco'] = mb_strtoupper(trim($this->contacto_parentesco), 'UTF-8');
        }

        if (!empty($dataToMerge)) {
            $this->merge($dataToMerge);
        }
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        $sometimes = $isUpdate ? 'sometimes|' : '';

        return [
            'nombres' => [$sometimes . 'required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ\s]+$/'],
            'apellidos' => [$sometimes . 'required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ\s]+$/'],
            'ci' => [
                $sometimes . 'required',
                'string',
                'regex:/^[0-9]{5,10}(-[A-Z])?$/',
                Rule::unique('miembros', 'ci')->ignore($this->route('miembro'), 'id_miembro')
            ],
            'celular' => [$sometimes . 'required', 'string', 'size:8', 'regex:/^[67][0-9]{7}$/'],
            'fecha' => 'nullable|date|before:today',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'direccion' => [$sometimes . 'required', 'string', 'min:10', 'max:200', 'regex:/^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ0-9\s.,#\-\/]+$/'],
            'id_categoria' => [$sometimes . 'required', 'integer', 'exists:categorias,id_categoria'],
            'id_seccion' => [$sometimes . 'required', 'integer', 'exists:secciones,id_seccion'],
            'id_rol' => [$sometimes . 'required', 'integer', 'exists:roles,id_rol'],
            'has_emergency_contact' => 'nullable|boolean',
            'contacto_nombre' => ['nullable', 'required_if:has_emergency_contact,true', 'string', 'min:3', 'max:100', 'regex:/^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ\s]+$/'],
            'contacto_parentesco' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ\s]*$/'],
            'contacto_celular' => ['nullable', 'required_if:has_emergency_contact,true', 'string', 'size:8', 'regex:/^[67][0-9]{7}$/'],
        ];
    }

    /**
     * Mensajes de error personalizados en español
     */
    public function messages(): array
    {
        return [
            // Nombres
            'nombres.required' => 'El nombre es obligatorio.',
            'nombres.min' => 'El nombre debe tener al menos 2 caracteres.',
            'nombres.max' => 'El nombre no puede exceder 50 caracteres.',
            'nombres.regex' => 'El nombre solo puede contener letras.',

            // Apellidos
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'apellidos.min' => 'Los apellidos deben tener al menos 2 caracteres.',
            'apellidos.max' => 'Los apellidos no pueden exceder 50 caracteres.',
            'apellidos.regex' => 'Los apellidos solo pueden contener letras.',

            // CI
            'ci.required' => 'El CI es obligatorio.',
            'ci.regex' => 'Formato de CI inválido. Use: 1234567 o 1234567-L (5-10 dígitos + opcional extensión).',
            'ci.unique' => 'Este CI ya está registrado en el sistema.',

            // Celular
            'celular.required' => 'El celular es obligatorio.',
            'celular.size' => 'El celular debe tener exactamente 8 dígitos.',
            'celular.regex' => 'El celular debe comenzar con 6 o 7 (Bolivia).',

            // Dirección
            'direccion.required' => 'La dirección es obligatoria.',
            'direccion.min' => 'La dirección debe tener al menos 10 caracteres.',
            'direccion.max' => 'La dirección no puede exceder 200 caracteres.',
            'direccion.regex' => 'La dirección contiene caracteres no permitidos.',

            // Fecha
            'fecha.before' => 'La fecha de nacimiento debe ser anterior a hoy.',

            // Catálogos
            'id_categoria.required' => 'Debe seleccionar una categoría.',
            'id_categoria.exists' => 'La categoría seleccionada no existe.',
            'id_seccion.required' => 'Debe seleccionar una sección.',
            'id_seccion.exists' => 'La sección seleccionada no existe.',
            'id_rol.required' => 'Debe seleccionar un rol.',
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
