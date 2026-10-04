<?php

namespace App\Http\Requests;

class AmenityRequest extends NameSlugRequest
{
    protected function table(): string
    {
        return 'amenities';
    }

    protected function routeParameter(): string
    {
        return 'amenity';
    }
}
