<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldOfStudy extends Model
{
    protected $table = 'fields_of_study'; // Explicitly set table name if convention fails, though plural is correct
    protected $fillable = ['name', 'category', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
