<?php

namespace App\Actions\Fortify;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $usuario = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        // Ningún usuario puede quedar sin su ficha personal (relación 1 a 1).
        $persona = Persona::create([
            'carnet' => 'USR'.str_pad((string) $usuario->id, 5, '0', STR_PAD_LEFT),
            'nombres' => $input['name'],
            'apellido_paterno' => $input['name'],
            'correo' => $input['email'],
        ]);

        $usuario->update(['persona_id' => $persona->id]);

        return $usuario;
    }
}
