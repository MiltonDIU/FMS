<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'subject',
        'body',
        'variables_json',
        'is_active',
    ];

    protected $casts = [
        'variables_json' => 'array',
        'is_active'      => 'boolean',
    ];

    /**
     * Replace dynamic placeholders in a template string for a given teacher.
     *
     * $extra carries values only some templates need, so the caller supplies
     * them rather than every template paying for them. The activation email
     * passes {activation_link} and {link_validity_days} this way, since the link
     * is only meaningful next to a freshly issued token.
     *
     * @param  array<string,string>  $extra
     */
    public static function replacePlaceholders(string $content, Teacher $teacher, array $extra = []): string
    {
        // Generate verification link safely
        if (\Illuminate\Support\Facades\Route::has('teacher.profile.verify')) {
            $verificationLink = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'teacher.profile.verify',
                now()->addDays(14),
                ['teacher' => $teacher->id, 'token' => $teacher->verification_token]
            );
        } else {
            $verificationLink = url("/admin/my-profile?token={$teacher->verification_token}");
        }

        $replacements = [
            '{teacher_name}'      => $teacher->full_name,
            '{employee_id}'       => $teacher->employee_id ?? 'N/A',
            '{department}'        => $teacher->department?->name ?? 'N/A',
            '{designation}'       => $teacher->designation?->name ?? 'N/A',
            '{profile_score}'     => ($teacher->profile_score ?? 0) . '%',
            '{verification_link}' => $verificationLink,
        ];

        $replacements = array_merge($replacements, $extra);

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
