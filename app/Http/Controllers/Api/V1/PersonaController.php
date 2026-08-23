<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PersonaResource;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Datos personales: nombre, carnet, celular, dirección, correo.
 *
 * Existe un solo sitio donde editarlos **a propósito**. La misma persona puede
 * ser a la vez cliente y trabajadora; si cada módulo tuviera su formulario,
 * corregir un celular habría que hacerlo dos veces y el sistema acabaría con
 * dos versiones del mismo dato.
 *
 * Las fichas de cliente y de trabajador guardan lo suyo —el código, el cargo,
 * la fecha de ingreso— y para lo demás apuntan aquí.
 */
class PersonaController extends Controller
{
    public function update(Request $request, Persona $persona): PersonaResource
    {
        $datos = $request->validate(self::reglas($persona), self::mensajes());

        $persona->update(self::aColumnas($datos));

        return new PersonaResource($persona->fresh());
    }

    /**
     * Reglas del panel (`App\Livewire\Personas\Index`), compartidas con el alta
     * de trabajador para que no puedan separarse sin darse cuenta.
     *
     * @return array<string, mixed>
     */
    public static function reglas(?Persona $persona): array
    {
        // Ni números ni signos raros en un nombre: el apóstrofo y el guion sí,
        // que aparecen en apellidos reales.
        $soloLetras = '/^[\p{L}\s\'\-]+$/u';

        return [
            'carnet' => [
                'required', 'string', 'regex:/^[0-9]{7,11}$/',
                Rule::unique('personas', 'carnet')->ignore($persona?->id)->whereNull('deleted_at'),
            ],
            'nombres' => ['required', 'string', 'min:2', 'max:100', "regex:{$soloLetras}"],
            // Al menos uno de los dos, no los dos: hay gente con un solo
            // apellido y exigir ambos obligaría a inventarse uno.
            'apellido_paterno' => ['required_without:apellido_materno', 'nullable', 'string', 'min:2', 'max:60', "regex:{$soloLetras}"],
            'apellido_materno' => ['required_without:apellido_paterno', 'nullable', 'string', 'min:2', 'max:60', "regex:{$soloLetras}"],
            'celular' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'correo' => [
                'nullable', 'email:rfc', 'max:150',
                Rule::unique('personas', 'correo')->ignore($persona?->id)->whereNull('deleted_at'),
            ],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
        ];
    }

    /**
     * Normaliza lo validado antes de guardarlo.
     *
     * Los opcionales vacíos van como NULL, nunca como cadena vacía: dos
     * personas sin correo chocarían contra el índice único si se guardara `''`.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function aColumnas(array $datos): array
    {
        $columnas = [
            'carnet' => $datos['carnet'],
            'nombres' => $datos['nombres'],
        ];

        foreach (['apellido_paterno', 'apellido_materno', 'celular', 'direccion', 'correo', 'fecha_nacimiento'] as $campo) {
            $valor = trim((string) ($datos[$campo] ?? ''));

            $columnas[$campo] = $valor === '' ? null : $valor;
        }

        return $columnas;
    }

    /**
     * Mensajes donde el genérico traducido no dice qué se espera.
     *
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'carnet.regex' => 'El carnet debe contener entre 7 y 11 números.',
            'carnet.unique' => 'Ya existe una persona registrada con este carnet.',
            'celular.regex' => 'El celular debe tener 8 números.',
            'correo.unique' => 'Ya existe una persona registrada con este correo.',
            'apellido_paterno.required_without' => 'Debes registrar al menos un apellido.',
            'apellido_materno.required_without' => 'Debes registrar al menos un apellido.',
        ];
    }
}
