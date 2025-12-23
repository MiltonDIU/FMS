<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicationQuartile extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'sort_order',
    ];
}
