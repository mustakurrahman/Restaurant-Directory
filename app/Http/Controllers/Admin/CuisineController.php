<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CuisineRequest;
use App\Models\Cuisine;

// All the behaviour is in NameSlugController; this only says what is special about cuisines
class CuisineController extends NameSlugController
{
    protected function model(): string
    {
        return Cuisine::class;
    }

    protected function requestClass(): string
    {
        return CuisineRequest::class;
    }

    protected function noun(): string
    {
        return 'cuisine';
    }

    protected function plural(): string
    {
        return 'cuisines';
    }

    protected function howToFreeIt(): string
    {
        return 'Untick it on those restaurants first (or delete the restaurants), then try again.';
    }
}
