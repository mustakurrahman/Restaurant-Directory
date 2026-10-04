<?php

namespace App\Http\Requests;

class CuisineRequest extends NameSlugRequest
{
    protected function table(): string
    {
        return 'cuisines';
    }

    protected function routeParameter(): string
    {
        return 'cuisine';
    }
}
