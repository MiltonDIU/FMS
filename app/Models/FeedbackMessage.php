<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a feedback thread: a reply, a status change, or both.
 *
 * Written only through Feedback, which keeps the status, whose turn it is and
 * the notifications in step with it.
 */
class FeedbackMessage extends Model
{
    protected $fillable = [
        'feedback_id',
        'user_id',
        'author_name',
        'is_staff',
        'is_internal',
        'body',
        'status_from',
        'status_to',
        'attachments',
    ];

    protected $casts = [
        'is_staff' => 'boolean',
        'is_internal' => 'boolean',
        'attachments' => 'array',
    ];

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
