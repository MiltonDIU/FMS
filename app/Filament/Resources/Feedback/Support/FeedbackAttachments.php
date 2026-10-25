<?php

namespace App\Filament\Resources\Feedback\Support;

use Filament\Forms\Components\FileUpload;

/**
 * The screenshot / file field, the same wherever feedback is written.
 *
 * On the public disk under random names, as the other uploads in the panel
 * are. Images and PDFs only: a screenshot of the error is what is usually
 * needed, and nothing here should be able to carry a script.
 */
class FeedbackAttachments
{
    public static function field(string $name = 'attachments'): FileUpload
    {
        return FileUpload::make($name)
            ->label('Screenshots or files')
            ->helperText('Optional. Up to 5 images or PDFs, 5 MB each. A screenshot of the error helps a great deal.')
            ->disk('public')
            ->directory('feedback')
            ->visibility('public')
            ->multiple()
            ->maxFiles(5)
            ->maxSize(5120)
            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf'])
            ->openable()
            ->downloadable();
    }
}
