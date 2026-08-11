<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * Dipakai sebagai middleware alias `role:admin`, `role:personil`, `role:tim`.
     * Redirect ke halaman yang sesuai role kalau akses ditolak — bukan 403 mentah,
     * supaya user tau harus ke mana.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles)) {
            // Kalau sudah login tapi role salah, redirect ke dashboard role mereka
            if ($user) {
                return redirect($this->dashboardRoute($user->role));
            }

            return redirect()->route('login');
        }

        return $next($request);
    }

    private function dashboardRoute(string $role): string
    {
        return match ($role) {
            'admin' => '/admin/dashboard',
            'personil' => '/jadwal-saya',
            'tim' => '/tim/ruangan',
            default => '/',
        };
    }
}
