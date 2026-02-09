<?php

namespace App\Filament\Pages;

use App\Services\ImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportTeachers extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-up-tray';



    protected static ?string $title = 'Import Teachers (Bulk)';
    protected  string $view = 'filament.pages.import-teachers';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Download Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    return response()->download(public_path('documents/sample_teachers_import.json'));
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Section::make('Bulk Import')
                    ->description('Upload a JSON file matching the standard template.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('Select JSON File')
                            ->acceptedFileTypes(['application/json'])
                            ->required(),

                        \Filament\Schemas\Components\Actions::make([
                            Action::make('import')
                                ->label('Start Import')
                                ->action('processImport')
                                ->color('primary'),
                        ])->fullWidth(),
                    ])
            ])
            ->statePath('data');
    }

    public function processImport()
    {
        $data = $this->form->getState();
        $file = $data['file'] ?? null;

        if (!$file) {
            Notification::make()->title('No file uploaded')->danger()->send();
            return;
        }

        // Resolve file path
        $path = is_array($file) ? reset($file) : $file;
        $fullPath = Storage::disk('public')->path($path);

        if (!file_exists($fullPath)) {
             $fullPath = Storage::path($path);
        }

        if (!file_exists($fullPath)) {
             Notification::make()->title('File expired or missing. Upload again.')->danger()->send();
             return;
        }

        $json = json_decode(file_get_contents($fullPath), true);

        if (!is_array($json)) {
            Notification::make()->title('Invalid JSON file.')->danger()->send();
            return;
        }

        $service = new ImportService();
        $result = $service->importTeachers($json);

        $count = $result['success_count'];
        $failed = $result['failed_records'];

        if ($count > 0) {
            Notification::make()
                ->title('Import Completed')
                ->body("Successfully imported {$count} teachers.")
                ->success()
                ->send();
        }

        if (!empty($failed)) {
            // Generate Error File
            $fileName = 'import_errors_' . time() . '.json';
            $content = json_encode($failed, JSON_PRETTY_PRINT);

            // Store it publicly so we can download it
            Storage::disk('public')->put("exports/{$fileName}", $content);

            Notification::make()
                ->title('Import Finished with Errors')
                ->body(count($failed) . " records failed. Download the error log to see details.")
                ->warning()
                ->persistent()
                ->actions([
                    Action::make('download')
                        ->label('Download Error Log')
                        ->url(Storage::disk('public')->url("exports/{$fileName}"), shouldOpenInNewTab: true)
                        ->button(),
                ])
                ->send();
        } else {
             $this->form->fill(); // Reset only if fully successful
        }
    }
}

