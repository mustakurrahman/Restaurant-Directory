<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * A "honeypot" is a form field that humans never see but simple spam robots fill in.
 * Used with <x-honeypot /> on every public form (reviews now; contact and submit-a-restaurant later).
 */
class Honeypot
{
    // Looks tempting to a robot; not a name a browser would auto-fill for a real person
    public const FIELD = 'company_website';

    public static function tripped(Request $request): bool
    {
        return filled($request->input(self::FIELD));
    }
}
