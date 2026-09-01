<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamOnboardingCompleted
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isTim()) {
            $isOnboardingRoute = $request->routeIs('tim.onboarding');

            if ($user->needsOnboarding() && ! $isOnboardingRoute) {
                return redirect()->route('tim.onboarding');
            }

            if (! $user->needsOnboarding() && $isOnboardingRoute) {
                return redirect()->route('tim.jadwal');
            }
        }

        return $next($request);
    }
}
