<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; "
            ."script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://maps.googleapis.com https://www.youtube.com; "
            ."style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.bunny.net; "
            ."font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com https://fonts.bunny.net; "
            ."img-src 'self' data: blob: https://*.googleapis.com https://*.gstatic.com https://*.google.com https://i.ytimg.com; "
            ."connect-src 'self' https://maps.googleapis.com https://*.googleapis.com; "
            ."frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://www.google.com; "
            ."worker-src 'self' blob: https://cdn.jsdelivr.net"
        );

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
