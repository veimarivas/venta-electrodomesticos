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
        return view('backend.profile.edit', [
            'title' => 'Mi perfil',
            'breadcrumbs' => ['Inicio' => route('dashboard'), 'Mi perfil' => null],
            'user' => auth()->user(),
        ]);
    }
}
