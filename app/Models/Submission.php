<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_name', 'address', 'city', 'cuisine',
        'phone', 'website', 'description',
        'submitter_name', 'submitter_email', 'status',
    ];
}
