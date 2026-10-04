<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = [
        'restaurant_name', 'address', 'city', 'cuisine',
        'phone', 'website', 'description',
        'submitter_name', 'submitter_email', 'status',
    ];
}
