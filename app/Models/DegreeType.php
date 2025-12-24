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

    protected static function booted()
    {
        static::creating(function ($degreeType) {
            if (empty($degreeType->slug)) {
                $degreeType->slug = \Illuminate\Support\Str::slug($degreeType->name);
            }
        });

        static::updating(function ($degreeType) {
            if ($degreeType->isDirty('name') && empty($degreeType->getOriginal('slug'))) { // Only update if name changed and slug wasn't manually set? Or always?
                // Usually better to keep slug stable unless explicitly requested.
                // But for new records it's essential.
            }
        });
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(DegreeLevel::class, 'degree_level_id');
    }
}
