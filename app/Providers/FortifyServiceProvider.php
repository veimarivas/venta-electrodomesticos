<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Único texto para la cuenta bloqueada. Lo usan tanto el login como el
     * middleware que corta la sesión de alguien desactivado en caliente.
     */
    public const MENSAJE_CUENTA_BLOQUEADA = 'Cuenta bloqueada, comunícate con el administrador.';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        $this->registerViews();
        $this->registerRateLimiters();
        $this->registerAuthentication();
    }

    /**
     * Autenticación propia: se entra con el nombre de usuario o con el correo.
     *
     * Fortify busca por una sola columna (config/fortify.php: 'username' =>
     * 'email'), pero las cuentas de los trabajadores se generan con un nombre
     * de usuario tipo "jperezlopez" y un correo que puede ser interno. Pedirles
     * memorizar el correo cuando lo que se les entregó fue el usuario no tiene
     * sentido, así que el campo del formulario acepta cualquiera de los dos.
     *
     * Aquí se resuelve también el bloqueo: una cuenta inactiva no entra, y el
     * mensaje lo dice en lugar de fingir que la contraseña está mal.
     */
    private function registerAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $identificador = Str::lower(trim((string) $request->input(Fortify::username())));

            // El correo primero: es único por índice. El nombre de usuario no
            // lo es a nivel de base, así que se desempata por el id más bajo
            // para que el resultado sea siempre el mismo.
            $usuario = User::where('email', $identificador)->first()
                ?? User::where('name', $identificador)->orderBy('id')->first();

            if ($usuario === null || ! Hash::check((string) $request->input('password'), $usuario->password)) {
                return null;
            }

            // Se comprueba después de validar la contraseña: si no, el mensaje
            // revelaría a cualquiera qué cuentas existen y cuáles están de baja.
            if (! $usuario->is_active) {
                throw ValidationException::withMessages([
                    Fortify::username() => self::MENSAJE_CUENTA_BLOQUEADA,
                ]);
            }

            return $usuario;
        });
    }

    /**
     * Fortify es "headless": no trae vistas propias, así que le indicamos
     * cuáles de la plantilla Velzon debe renderizar en cada paso.
     */
    private function registerViews(): void
    {
        Fortify::loginView(fn () => view('backend.auth.login'));

        Fortify::requestPasswordResetLinkView(fn () => view('backend.auth.forgot-password'));

        Fortify::resetPasswordView(fn (Request $request) => view('backend.auth.reset-password', [
            'request' => $request,
        ]));

        Fortify::twoFactorChallengeView(fn () => view('backend.auth.two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => view('backend.auth.confirm-password'));
    }

    /**
     * Límites de intentos: protegen el login contra fuerza bruta.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
