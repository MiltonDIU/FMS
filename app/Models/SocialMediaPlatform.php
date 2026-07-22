<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialMediaPlatform extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'icon_class',
        'base_url',
        'is_active',
        'sort_order',
        'allow_multiple',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_multiple' => 'boolean',
    ];

    /**
     * Build full URL for a username based on base_url format.
     */
    public function buildUrl(?string $username): ?string
    {
        if (!$this->base_url || !$username) {
            return null;
        }

        $username = trim($username);

        // If username is already a full URL, return it directly
        if (str_starts_with($username, 'http://') || str_starts_with($username, 'https://')) {
            return $username;
        }

        $baseUrl = $this->base_url;

        if (str_ends_with($baseUrl, '=') || str_ends_with($baseUrl, '?') || str_ends_with($baseUrl, '/')) {
            return $baseUrl . ltrim($username, '/');
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($username, '/');
    }
}

