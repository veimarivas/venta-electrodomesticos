<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
        // El administrador pasa por alto cualquier verificación de permisos:
        // así no hay que acordarse de asignarle cada permiso nuevo.
        Gate::before(fn ($user) => $user->hasRole('admin') ? true : null);

        // En un sistema con dinero de por medio conviene fallar temprano.
        // Se activa también en testing (no solo en local): así la suite detecta
        // atributos inexistentes, que si no devuelven null en silencio.
        Model::shouldBeStrict(! $this->app->isProduction());

        // La plantilla Velzon es Bootstrap 5: la paginación debe usar esas
        // clases, si no Livewire renderiza los enlaces de Tailwind.
        Paginator::useBootstrapFive();

        // En producción todo enlace y todo formulario se generan con https.
        // Detrás de un proxy que termina el TLS, Laravel ve la petición como
        // http y produciría URLs http:// dentro de una página https://: el
        // navegador bloquea esas peticiones como contenido mixto y el panel
        // se queda sin CSS ni WebSocket.
        URL::forceHttps($this->app->isProduction());

        Vite::prefetch(concurrency: 3);
    }
}
