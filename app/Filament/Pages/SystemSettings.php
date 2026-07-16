<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

class SystemSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.system-settings';

    protected static ?string $slug = 'settings';

    protected static ?string $title = 'System Settings';

    public static function canAccess(): bool
    {
        return auth()->user()->can('View:SystemSettings', Setting::class);
    }

    public static function getAvailableThemes(): array
    {
        $themesPath = resource_path('views/frontend/themes');
        $themes = [];
        if (is_dir($themesPath)) {
            $dirs = array_filter(glob($themesPath . '/*'), 'is_dir');
            foreach ($dirs as $dir) {
                $slug = basename($dir);
                $name = str_replace(['_', '-'], ' ', $slug);
                $themes[$slug] = ucwords($name);
            }
        }
        if (empty($themes)) {
            $themes['theme_default'] = 'Theme Default';
        }
        return $themes;
    }

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        // Convert boolean strings
        foreach ($settings as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting && $setting->type === 'boolean') {
                $settings[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        // Explicitly cast custom boolean settings
        $boolKeys = ['export_overwrite', 'import_dry_run', 'import_skip_existing'];
        foreach ($boolKeys as $bk) {
            if (isset($settings[$bk])) {
                $settings[$bk] = filter_var($settings[$bk], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $this->form->fill(array_merge([
            'export_limit' => 0,
            'export_provider' => 'auto',
            'export_overwrite' => false,
            'import_limit' => 0,
            'import_dry_run' => false,
            'import_skip_existing' => true,
            'teacher_login_mode' => 'individual',
            'frontend_driver' => 'blade',
            'nextjs_url' => '',
            'active_theme' => 'theme_default',
            'diu_color_palette' => 'diu',
            'diu_primary_color' => null,
        ] + array_fill_keys(\App\Helpers\ColorPalette::OVERRIDE_KEYS, null), $settings));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Settings')
                    ->tabs([
                        Tab::make('Teacher Settings')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Section::make('Account Creation')
                                    ->description('Settings for new teacher account creation')
                                    ->schema([
                                        TextInput::make('teacher_default_password')
                                            ->label('Default Password')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Password for newly created teacher accounts'),
                                        Toggle::make('teacher_send_welcome_email')
                                            ->label('Send Welcome Email')
                                            ->helperText('Automatically send login credentials to new teachers'),
                                    ]),
                                Section::make('Teacher Login Control')
                                    ->description('Configure login access modes for teachers')
                                    ->schema([
                                        \Filament\Forms\Components\Select::make('teacher_login_mode')
                                            ->label('Teacher Login Mode')
                                            ->options([
                                                'individual' => 'Individual Settings Only',
                                                'allow_all' => 'Allow All Active Teachers (Override)',
                                                'disable_all' => 'Disable All Teacher Logins',
                                            ])
                                            ->default('individual')
                                            ->required(),
                                    ]),
                            ]),
                        Tab::make('Dashboard Settings')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Section::make('Performance')
                                    ->schema([
                                        Toggle::make('check_package_updates')
                                            ->label('Check for Package Updates')
                                            ->helperText('Enabling this may slow down the dashboard load time as it checks for latest versions.'),
                                    ]),
                            ]),
                        Tab::make('Mail Configuration')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Section::make('SMTP Settings')
                                    ->description('Configure your email server settings')
                                    ->schema([
                                        \Filament\Forms\Components\Select::make('mail_mailer')
                                            ->label('Mailer')
                                            ->options([
                                                'smtp' => 'SMTP',
                                                'log' => 'Log (Local Debugging)',
                                            ])
                                            ->default('smtp')
                                            ->required(),
                                        TextInput::make('mail_host')
                                            ->label('Host')
                                            ->default('smtp.gmail.com')
                                            ->required(),
                                        TextInput::make('mail_port')
                                            ->label('Port')
                                            ->numeric()
                                            ->default(587)
                                            ->required(),
                                        TextInput::make('mail_username')
                                            ->label('Username')
                                            ->required(),
                                        TextInput::make('mail_password')
                                            ->label('Password')
                                            ->password()
                                            ->revealable(),
                                        TextInput::make('mail_encryption')
                                            ->label('Encryption')
                                            ->default('tls'),
                                    ])->columns(2),
                                Section::make('Sender Identity')
                                    ->schema([
                                        TextInput::make('mail_from_address')
                                            ->label('From Address')
                                            ->email()
                                            ->required(),
                                        TextInput::make('mail_from_name')
                                            ->label('From Name')
                                            ->default(config('app.name')),
                                    ])->columns(2),
                            ]),
                        Tab::make('Frontend Settings')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Section::make('Frontend Configuration')
                                    ->description('Choose how the public teacher portal is served. The "Reset Colors to Default" button restores the original DIU theme colors and clears all manual overrides below.')
                                    ->schema([
                                        \Filament\Forms\Components\Select::make('frontend_driver')
                                            ->label('Frontend Driver')
                                            ->options([
                                                'blade' => 'Laravel Blade (Monolith)',
                                                'nextjs' => 'Next.js (Headless Redirect)',
                                            ])
                                            ->default('blade')
                                            ->live()
                                            ->required(),
                                        TextInput::make('nextjs_url')
                                            ->label('Next.js App URL')
                                            ->url()
                                            ->placeholder('https://teachers.diu.edu.bd')
                                            ->requiredIf('frontend_driver', 'nextjs')
                                            ->visible(fn ($get) => $get('frontend_driver') === 'nextjs')
                                            ->helperText('Public web visitors will be redirected to this URL'),
                                        \Filament\Forms\Components\Select::make('active_theme')
                                            ->label('Active Theme')
                                            ->options(fn () => static::getAvailableThemes())
                                            ->default('theme_default')
                                            ->required(),
                                        \Filament\Forms\Components\Select::make('diu_color_palette')
                                            ->label('Color Palette')
                                            ->options(\App\Helpers\ColorPalette::presetOptions())
                                            ->default('diu')
                                            ->required()
                                            ->helperText('Auto-generates the full color scheme from a single base color.'),
                                        \Filament\Forms\Components\ColorPicker::make('diu_primary_color')
                                            ->label('Custom Primary Color (optional)')
                                            ->hex()
                                            ->default(null)
                                            ->helperText('Overrides the palette base color if set. Leave empty to use the selected palette.'),
                                    ]),

                                \Filament\Schemas\Components\Actions::make([
                                    \Filament\Actions\Action::make('reset_colors')
                                        ->label('Reset Colors to Default')
                                        ->icon('heroicon-o-arrow-path')
                                        ->color('gray')
                                        ->requiresConfirmation()
                                        ->action('resetColors'),
                                ]),

                                \Filament\Schemas\Components\Section::make('Manual Color Overrides')
                                    ->description('Fine-tune individual colors. Each is used in specific places across the site — see the hint under each picker. Overrides are layered on top of the generated palette, so they persist until you clear them or hit Reset.')
                                    ->collapsed()
                                    ->schema(
                                        collect(\App\Helpers\ColorPalette::colorFields())
                                            ->map(function ($f) {
                                                return \Filament\Forms\Components\ColorPicker::make($f['key'])
                                                    ->label($f['label'])
                                                    ->hex()
                                                    ->default(null)
                                                    ->helperText($f['usage']);
                                            })
                                            ->all()
                                    ),
                            ]),

                        Tab::make('Data Migration')
                            ->icon('heroicon-o-arrow-path')
                            ->visible(fn() => env('SHOW_DATA_MIGRATION_TAB', false))
                            ->schema([
                                Section::make('Background Data Export')
                                    ->description('Export data from the old database into JSON export files in the background using AI parsing.')
                                    ->poll('5s')
                                    ->schema([
                                        \Filament\Forms\Components\Placeholder::make('export_progress')
                                            ->label('Current Progress')
                                            ->content(function() {
                                                $status = Setting::get('export_progress', 'Idle');
                                                if (str_starts_with($status, 'Running:') && !\Illuminate\Support\Facades\DB::table('jobs')->exists()) {
                                                    $status = 'Idle';
                                                    Setting::set('export_progress', 'Idle');
                                                }
                                                return $status;
                                            }),
                                        TextInput::make('export_limit')
                                            ->label('Export Limit')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('Limit the number of teachers processed per command (0 = all)'),
                                        \Filament\Forms\Components\Select::make('export_provider')
                                            ->label('AI Provider')
                                            ->options([
                                                'auto' => 'Auto-Detect',
                                                'vertex' => 'Vertex AI (Gemini)',
                                                'gemini' => 'Gemini API',
                                                'openrouter' => 'OpenRouter',
                                                'groq' => 'Groq',
                                                'anthropic' => 'Anthropic',
                                                'deepseek' => 'DeepSeek',
                                                'heuristic' => 'Heuristic (Rule-based / No AI)',
                                            ])
                                            ->default('auto')
                                            ->required(),
                                        Toggle::make('export_overwrite')
                                            ->label('Overwrite Existing Exports')
                                            ->helperText('Re-process and overwrite already parsed files'),
                                        \Filament\Schemas\Components\Actions::make([
                                            Action::make('run_export')
                                                ->label('Start Background Export')
                                                ->action('startBackgroundExport')
                                                ->color('warning')
                                                ->icon('heroicon-o-arrow-up-tray'),
                                        ]),
                                    ]),
                                Section::make('Background Data Import')
                                    ->description('Import parsed JSON data into the FMS database in the background.')
                                    ->poll('5s')
                                    ->schema([
                                        \Filament\Forms\Components\Placeholder::make('import_progress')
                                            ->label('Current Progress')
                                            ->content(function() {
                                                $status = Setting::get('import_progress', 'Idle');
                                                if (str_starts_with($status, 'Running:') && !\Illuminate\Support\Facades\DB::table('jobs')->exists()) {
                                                    $status = 'Idle';
                                                    Setting::set('import_progress', 'Idle');
                                                }
                                                return $status;
                                            }),
                                        TextInput::make('import_limit')
                                            ->label('Import Limit')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('Limit the number of records processed per command (0 = all)'),
                                        Toggle::make('import_dry_run')
                                            ->label('Dry Run')
                                            ->helperText('Validate and preview records without writing to the database'),
                                        Toggle::make('import_skip_existing')
                                            ->label('Skip Existing')
                                            ->default(true)
                                            ->helperText('Skip records if they are already imported'),
                                        \Filament\Schemas\Components\Actions::make([
                                            Action::make('run_import')
                                                ->label('Start Background Import')
                                                ->action('startBackgroundImport')
                                                ->color('success')
                                                ->icon('heroicon-o-arrow-down-tray'),
                                        ]),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        // Clear cached color settings so the frontend reflects changes at once.
        if (array_intersect(array_keys($data), array_merge(
            ['diu_color_palette', 'diu_primary_color'],
            \App\Helpers\ColorPalette::OVERRIDE_KEYS
        ))) {
            \App\Helpers\ColorPalette::forgetCache();
        }

        Notification::make()
            ->success()
            ->title('Settings saved successfully')
            ->send();
    }

    public function resetColors(): void
    {
        \App\Helpers\ColorPalette::resetToDefaults();

        // Re-fill the form so the UI reflects the restored defaults.
        $this->form->fill(array_merge(
            $this->form->getState(),
            ['diu_color_palette' => 'diu', 'diu_primary_color' => null]
            + array_fill_keys(\App\Helpers\ColorPalette::OVERRIDE_KEYS, null)
        ));

        Notification::make()
            ->success()
            ->title('Colors reset to DIU defaults')
            ->send();
    }

    public function startBackgroundExport(): void
    {
        $limit = (int) ($this->data['export_limit'] ?? 0);
        $provider = $this->data['export_provider'] ?? 'auto';
        $overwrite = (bool) ($this->data['export_overwrite'] ?? false);

        \App\Jobs\RunMasterExportJob::dispatch($limit, $provider, $overwrite);

        Notification::make()
            ->success()
            ->title('Master Export Job Dispatched!')
            ->body('The export process has been queued in the background. You can monitor it in Telescope under the Jobs tab.')
            ->send();
    }

    public function startBackgroundImport(): void
    {
        $limit = (int) ($this->data['import_limit'] ?? 0);
        $dryRun = (bool) ($this->data['import_dry_run'] ?? false);
        $skipExisting = (bool) ($this->data['import_skip_existing'] ?? true);

        \App\Jobs\RunMasterImportJob::dispatch($limit, $dryRun, $skipExisting);

        Notification::make()
            ->success()
            ->title('Master Import Job Dispatched!')
            ->body('The import process has been queued in the background. You can monitor it in Telescope under the Jobs tab.')
            ->send();
    }

    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }
}
