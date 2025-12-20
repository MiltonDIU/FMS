<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Publication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'type',
        'title',
        'authors',
        'journal_name',
        'publisher',
        'indexed_by',
        'doi',
        'url',
        'volume',
        'issue',
        'pages',
        'publication_year',
        'country',
        'keywords',
        'abstract',
        'is_international',
        'status',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'is_international' => 'boolean',
        'is_featured' => 'boolean',
    ];

    /**
     * Get the teacher that owns the publication.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
