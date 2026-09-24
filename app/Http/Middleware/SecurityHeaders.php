<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * Adds security headers to prevent XSS, clickjacking, and other attacks.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking (website embedded in iframe)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Enable XSS filter in older browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy - Allow inline scripts for Alpine.js/Livewire
        // In development, allow Vite dev server (localhost:5173)
        $scriptSrc = "'self' 'unsafe-inline' 'unsafe-eval'";
        $styleSrc = "'self' 'unsafe-inline'";
        $connectSrc = "'self'";
        $fontSrc = "'self' data:";

        if (app()->environment('local')) {
            $scriptSrc .= ' http://localhost:5173';
            $styleSrc .= ' http://localhost:5173';
            $connectSrc .= ' http://localhost:5173 ws://localhost:5173';
            $fontSrc .= ' http://localhost:5173';

            // Allow Cloudflare Tunnel domain
            $appUrl = config('app.url');
            if (str_contains($appUrl, 'trycloudflare.com')) {
                $domain = parse_url($appUrl, PHP_URL_HOST);
                $scriptSrc .= " https://{$domain} http://{$domain}";
                $styleSrc .= " https://{$domain} http://{$domain}";
                $connectSrc .= " https://{$domain} http://{$domain} wss://{$domain} ws://{$domain}";
                $fontSrc .= " https://{$domain} http://{$domain}";
            }
        }

        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; ".
            "script-src {$scriptSrc}; ".
            "style-src {$styleSrc}; ".
            "img-src 'self' data: https:; ".
            "font-src {$fontSrc}; ".
            "connect-src {$connectSrc};"
        );

        // Permissions Policy (formerly Feature-Policy)
        $response->headers->set('Permissions-Policy',
            'camera=(), microphone=(), geolocation=()'
        );

        return $response;
    }
}
