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
     * Middleware alias: `role:admin`, `role:tim`
     * (role 'personil' sudah dihapus — merge ke 'tim')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles)) {
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
            'tim' => '/jadwal-tim',
            default => '/',
        };
    }
}
