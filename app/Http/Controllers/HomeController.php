<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Redirect user ke dashboard sesuai role setelah login.
     * Route `/` hanya diakses kalau sudah auth — kalau belum, middleware
     * `auth` akan redirect ke /login otomatis.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect('/admin/dashboard');
        }

        if ($user->isTim()) {
            if ($user->needsOnboarding()) {
                return redirect()->route('tim.onboarding');
            }

            return redirect()->route('tim.beranda');
        }

        return redirect('/login');
    }
}
