<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CityRequest;
use App\Models\City;

// All the behaviour is in NameSlugController; this only says what is special about cities
class CityController extends NameSlugController
{
    protected function model(): string
    {
        return City::class;
    }

    protected function requestClass(): string
    {
        return CityRequest::class;
    }

    protected function noun(): string
    {
        return 'city';
    }

    protected function plural(): string
    {
        return 'cities';
    }

    protected function features(): array
    {
        return ['description'];
    }

    protected function howToFreeIt(): string
    {
        return 'Move them to another city or delete them first, then try again.';
    }
}
