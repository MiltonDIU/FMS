<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrantType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'sort_order',
    ];
}
