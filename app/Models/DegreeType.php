<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DegreeType extends Model
{
    protected $fillable = ['degree_level_id', 'code', 'name','slug', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(DegreeLevel::class, 'degree_level_id');
    }
}
