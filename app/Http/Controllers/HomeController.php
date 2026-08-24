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
        return match ($request->user()->role) {
            'admin' => redirect('/admin/dashboard'),
            'tim' => redirect('/jadwal-tim'),
            default => redirect('/login'),
        };
    }
}
