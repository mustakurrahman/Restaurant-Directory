<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Many hosts put a "proxy" (a gateway such as Cloudflare, a load balancer or the host's own front server) in
    | front of your site. Then every visitor appears to come from the proxy's address, so the spam rate limits would
    | count all visitors as ONE person, and Laravel would think the site is on plain http.
    |
    | TRUSTED_PROXIES in .env tells Laravel which proxies to believe:
    |   (empty)           no proxy: trust nothing (the safe default; right for a server visitors reach directly)
    |   *                 trust whatever connects: only use this if your server can ONLY be reached through the proxy
    |   10.0.0.1,10.0.0.2 trust exactly these addresses
    |
    | Laravel's TrustProxies middleware reads this value (config('trustedproxy.proxies')).
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
