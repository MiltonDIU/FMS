<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmploymentStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'check_active',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'check_active' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }
}
