<?php

namespace App\Http\Controllers;

use App\Services\Sitemap;

class SeoController extends Controller
{
    // /sitemap.xml: every public page, for search engines
    public function sitemap(Sitemap $sitemap)
    {
        return response($sitemap->xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            // An hour is fresh enough for search engines and spares the database from repeated requests
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    // /robots.txt: tells search engines what they may visit. It is generated (not a fixed file) so it can include
    // the full sitemap address and so test or staging sites are never indexed by accident.
    public function robots()
    {
        if (! app()->environment('production')) {
            $lines = ['User-agent: *', 'Disallow: /']; // only the real website should appear in Google
        } else {
            $lines = [
                'User-agent: *',
                'Disallow: /admin', // the admin pages are also marked noindex; this stops crawlers wasting visits there
                '',
                'Sitemap: '.route('sitemap'),
            ];
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
