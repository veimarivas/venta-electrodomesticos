<?php

namespace Database\Factories;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Debe ir explícito: aunque la columna tiene DEFAULT true, el
            // modelo recién creado no lo hidrata y el middleware 'active'
            // interpretaría el null como cuenta desactivada.
            'is_active' => true,
            // Debe ir explícito: el accesor avatar_url lo lee y, en modo
            // estricto, un atributo ausente lanza excepción.
            'avatar_path' => null,
            // La relación es obligatoria: ningún usuario existe sin su persona.
            // Se usa un nombre genérico y un carnet con prefijo para que las
            // búsquedas de los tests no colisionen con la ficha autogenerada.
            'persona_id' => Persona::factory()->state([
                'carnet' => 'SYS'.fake()->unique()->numberBetween(1000000, 99999999),
                'nombres' => 'Usuario',
                'apellido_paterno' => 'Sistema',
                'apellido_materno' => null,
                'celular' => null,
                'direccion' => null,
                'correo' => null,
            ]),
        ];
    }

    /**
     * Cuenta dada de baja: conserva su historial pero no puede entrar.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
