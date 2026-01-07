<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DegreeLevel extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order','is_active', 'is_report','description'];

    public function degreeTypes(): HasMany
    {
        return $this->hasMany(DegreeType::class, 'degree_level_id');
    }
}
