<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'social_media_platform_id',
        'username',
        'url',
        'sort_order',
    ];

    /**
     * Get the teacher that owns the social link.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(SocialMediaPlatform::class, 'social_media_platform_id');
    }
}
