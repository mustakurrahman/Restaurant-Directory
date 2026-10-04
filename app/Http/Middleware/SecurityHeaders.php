<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds standard browser protections to every response (pages, errors, sitemap, robots).
 *
 * Not included on purpose: a Content-Security-Policy. The site uses small inline scripts (the delete confirmation),
 * so a strict policy would break it until those are moved into app.js. That is on the Version 2 list.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');                       // browsers must trust the declared file type (stops uploads being run as scripts)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');                           // other websites cannot show ours inside a frame (click-jacking)
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');      // other sites only learn our domain, not the full page address
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()'); // the site never needs these browser features

        // Tells browsers to use https for a year. Only sent over https in production: on plain http it is ignored,
        // and sending it from a test or local site could lock a browser into https for that address.
        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
