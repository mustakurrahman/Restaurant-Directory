<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\AmenityRequest;
use App\Models\Amenity;

// All the behaviour is in NameSlugController; this only says what is special about amenities
class AmenityController extends NameSlugController
{
    protected function model(): string
    {
        return Amenity::class;
    }

    protected function requestClass(): string
    {
        return AmenityRequest::class;
    }

    protected function noun(): string
    {
        return 'amenity';
    }

    protected function plural(): string
    {
        return 'amenities';
    }

    protected function features(): array
    {
        return ['icon'];
    }

    protected function howToFreeIt(): string
    {
        return 'Untick it on those restaurants first (or delete the restaurants), then try again.';
    }
}
