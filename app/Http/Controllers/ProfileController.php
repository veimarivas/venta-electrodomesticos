<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class ProfileController extends Controller
{
    /**
     * Pantalla de perfil. Los formularios envían a las rutas de Fortify:
     * user/profile-information, user/password y user/two-factor-authentication.
     */
    public function edit(): View
    {
        // Sin `title`: la cabecera del perfil ya es el encabezado de la
        // pantalla; pasar título aquí pintaba una segunda cabecera encima.
        return view('backend.profile.edit', [
            'user' => auth()->user(),
        ]);
    }
}
