<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        if (Hash::check($input['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'La nueva contraseña tiene que ser distinta de la actual.',
            ])->errorBag('updatePassword');
        }

        $user->forceFill([
            'password' => Hash::make($input['password']),
            // Ya tiene una propia: se deja de pedir.
            'debe_cambiar_password' => false,
        ])->save();
    }
}
