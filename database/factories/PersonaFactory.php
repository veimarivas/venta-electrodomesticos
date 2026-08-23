<?php

namespace Database\Factories;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Persona>
 */
class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carnet' => (string) fake()->unique()->numberBetween(1000000, 99999999),
            'nombres' => fake()->firstName(),
            // lastName() a veces genera apellidos con apóstrofo o guion, que
            // la validación del formulario no admite: se dejan solo las letras.
            'apellido_paterno' => $this->apellidoLetras(),
            'apellido_materno' => $this->apellidoLetras(),
            'celular' => (string) fake()->numberBetween(60000000, 79999999),
            'direccion' => fake()->address(),
            'correo' => fake()->unique()->safeEmail(),
            'fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
        ];
    }

    /**
     * Apellido formado solo por letras (válido para la regla de validación);
     * si la normalización dejara el texto vacío, se usa un apellido por defecto.
     */
    private function apellidoLetras(): string
    {
        $apellido = Str::of(fake()->lastName())
            ->replaceMatches('/[^\p{L}]/u', '')
            ->value();

        return $apellido === '' ? 'Apellido' : $apellido;
    }
}
