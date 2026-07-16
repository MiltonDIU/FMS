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
                // Only list themes that are actually usable: a complete theme
                // must ship its own layout, otherwise selecting it would crash
                // the frontend. This keeps stub/broken theme folders out of the
                // Active Theme dropdown.
                if (! is_file($dir . '/layouts/app.blade.php')) {
                    continue;
                }
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
        foreach (array_keys(static::getAvailableThemes()) as $slug) {
            $boolKeys[] = \App\Helpers\FontManager::settingKey($slug, 'footer_match_theme');
        }
        foreach ($boolKeys as $bk) {
            if (isset($settings[$bk])) {
                $settings[$bk] = filter_var($settings[$bk], FILTER_VALIDATE_BOOLEAN);
            }
        }

        // Show the auto-generated (palette) value in each override field so
        // the admin can see the color currently in use. An empty stored
        // override means "use the palette", so we surface the generated value
        // as the visible input while keeping the underlying state null.
        foreach (\App\Helpers\ColorPalette::OVERRIDE_KEYS as $key) {
            if (empty($settings[$key])) {
                $settings[$key] = \App\Helpers\ColorPalette::defaultValueFor($key);
            }
        }

        $settings['global_custom_fonts'] = \App\Helpers\FontManager::customFonts();

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
            'global_custom_fonts' => [],
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
                                    ->columns(2)
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

                                \Filament\Schemas\Components\Section::make('Global Custom Font Library')
                                    ->description('Upload and manage custom web fonts (.woff2, .ttf, .otf, .sfnt). Once uploaded, you can select these fonts in any of the theme dropdowns below.')
                                    ->collapsed()
                                    ->schema([
                                        \Filament\Forms\Components\Repeater::make('global_custom_fonts')
                                            ->label('Uploaded Fonts')
                                            ->schema([
                                                \Filament\Forms\Components\Hidden::make('id'),
                                                \Filament\Forms\Components\Hidden::make('format'),
                                                \Filament\Forms\Components\Hidden::make('weight'),
                                                \Filament\Forms\Components\TextInput::make('name')
                                                    ->label('Font Name')
                                                    ->required()
                                                    ->placeholder('My Custom Font'),
                                                \Filament\Forms\Components\FileUpload::make('file')
                                                    ->label('Upload Font File')
                                                    ->disk('public')
                                                    ->directory('fonts')
                                                    ->acceptedFileTypes(['font/woff2', 'font/woff', 'font/ttf', 'font/otf', 'font/sfnt', 'application/font-woff', 'application/font-woff2', 'application/x-font-ttf', 'application/vnd.ms-opentype'])
                                                    ->maxSize(5120)
                                                    ->helperText('Upload a .woff2 / .ttf / .otf / .sfnt file.'),
                                                \Filament\Forms\Components\TextInput::make('url')
                                                    ->label('Or External Font URL')
                                                    ->url()
                                                    ->placeholder('https://example.com/font.woff2 or Google css2 link')
                                                    ->helperText('Use if hosting the font externally or linking Google stylesheet.'),
                                                \Filament\Forms\Components\TextInput::make('family')
                                                    ->label('CSS font-family Name')
                                                    ->placeholder('My Custom Font')
                                                    ->helperText('Required when using an external URL so we know the family name to reference.'),
                                            ])
                                            ->columns(4)
                                            ->default([])
                                            ->createItemButtonLabel('Add Custom Font')
                                    ]),

                                ...self::fontSections(),

                                \Filament\Schemas\Components\Section::make('Manual Color Overrides')
                                    ->description('Each color is used in a specific place on the site (shown under the picker). Leave a field empty to let the selected palette generate it automatically; fill only the ones you want to change. The "Auto default" shown is what the palette produces. Use "Reset Colors to Default" above to restore everything at once.')
                                    ->collapsed()
                                    ->columns(3)
                                    ->schema(
                                        collect(\App\Helpers\ColorPalette::colorFields())
                                            ->map(function ($f) {
                                                $default = \App\Helpers\ColorPalette::defaultValueFor($f['key']);
                                                return \Filament\Forms\Components\ColorPicker::make($f['key'])
                                                    ->label($f['label'])
                                                    ->hex()
                                                    ->default($default)
                                                    ->helperText($f['usage'] . ' (Auto default: ' . ($default ?? 'n/a') . ')');
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

    /**
     * Build per-theme font control sections (font roles + "Install New Font").
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected static function fontSections(): array
    {
        $themes = array_keys(static::getAvailableThemes());

        return collect($themes)->map(function ($slug) {
            $label = ucwords(str_replace(['_', '-'], ' ', $slug));

            return \Filament\Schemas\Components\Section::make("Typography & Layout — {$label}")
                ->description('Configure typography settings, sizing, and footer styles for this theme.')
                ->collapsed()
                ->columns(3)
                ->schema([
                    \Filament\Forms\Components\Select::make(\App\Helpers\FontManager::settingKey($slug, 'font_sans'))
                        ->label('Body / Sans Font')
                        ->options(fn () => \App\Helpers\FontManager::optionsForSelect($slug))
                        ->default(\App\Helpers\FontManager::DEFAULTS['sans'])
                        ->searchable()
                        ->helperText('Used for body text (font-sans).'),
                    \Filament\Forms\Components\Select::make(\App\Helpers\FontManager::settingKey($slug, 'font_display'))
                        ->label('Display / Heading Font')
                        ->options(fn () => \App\Helpers\FontManager::optionsForSelect($slug))
                        ->default(\App\Helpers\FontManager::DEFAULTS['display'])
                        ->searchable()
                        ->helperText('Used for headings (font-display).'),
                    \Filament\Forms\Components\Select::make(\App\Helpers\FontManager::settingKey($slug, 'font_mono'))
                        ->label('Mono / Code Font')
                        ->options(fn () => \App\Helpers\FontManager::optionsForSelect($slug))
                        ->default(\App\Helpers\FontManager::DEFAULTS['mono'])
                        ->searchable()
                        ->helperText('Used for code/mono text (font-mono).'),

                    \Filament\Schemas\Components\Grid::make(4)
                        ->columnSpanFull()
                        ->schema([
                            \Filament\Forms\Components\Select::make(\App\Helpers\FontManager::settingKey($slug, 'font_base_size'))
                                ->label('Base Font Size (Root)')
                                ->options([
                                    '13px' => '13px',
                                    '14px' => '14px',
                                    '15px' => '15px',
                                    '16px' => '16px (Default)',
                                    '17px' => '17px',
                                    '18px' => '18px',
                                    '19px' => '19px',
                                    '20px' => '20px',
                                    '21px' => '21px',
                                    '22px' => '22px',
                                    '23px' => '23px',
                                    '24px' => '24px',
                                ])
                                ->default('16px'),
                            \Filament\Forms\Components\Select::make(\App\Helpers\FontManager::settingKey($slug, 'font_sans_weight'))
                                ->label('Body Font Weight')
                                ->options([
                                    '300' => 'Light (300)',
                                    '400' => 'Normal (400)',
                                    '500' => 'Medium (500)',
                                    '600' => 'Semibold (600)',
                                    '700' => 'Bold (700)',
                                ])
                                ->default('400'),
                            \Filament\Forms\Components\Select::make(\App\Helpers\FontManager::settingKey($slug, 'font_display_weight'))
                                ->label('Heading Font Weight')
                                ->options([
                                    '300' => 'Light (300)',
                                    '400' => 'Normal (400)',
                                    '500' => 'Medium (500)',
                                    '600' => 'Semibold (600)',
                                    '700' => 'Bold (700)',
                                    '800' => 'Extra Bold (800)',
                                ])
                                ->default('700'),
                            \Filament\Forms\Components\Select::make(\App\Helpers\FontManager::settingKey($slug, 'font_mono_weight'))
                                ->label('Mono Font Weight')
                                ->options([
                                    '300' => 'Light (300)',
                                    '400' => 'Normal (400)',
                                    '500' => 'Medium (500)',
                                    '600' => 'Semibold (600)',
                                    '700' => 'Bold (700)',
                                ])
                                ->default('400'),
                        ]),

                    \Filament\Forms\Components\Toggle::make(\App\Helpers\FontManager::settingKey($slug, 'footer_match_theme'))
                        ->label('Match Footer Background with Theme Color')
                        ->helperText('If enabled, the footer background will dynamically use the dark primary color of the theme instead of static black.')
                        ->default(false)
                        ->columnSpanFull(),
                ]);
        })->values()->all();
    }

    protected static function fontFormatFromPath(string $path): string
    {
        $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
        return match ($ext) {
            'woff2' => 'woff2',
            'woff'  => 'woff',
            'ttf'   => 'truetype',
            'otf'   => 'opentype',
            default => 'woff2',
        };
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (isset($data['global_custom_fonts']) && is_array($data['global_custom_fonts'])) {
            foreach ($data['global_custom_fonts'] as $index => $font) {
                $file = $font['file'] ?? null;
                if (is_array($file)) {
                    $file = reset($file);
                }
                $font['file'] = is_string($file) ? $file : null;

                $fileOrUrl = $font['file'] ?: ($font['url'] ?? '');

                if (empty($font['id'])) {
                    $font['id'] = 'f' . substr(md5($fileOrUrl . time() . $index), 0, 8);
                }

                if (empty($font['format'])) {
                    $font['format'] = $font['file'] ? self::fontFormatFromPath($font['file']) : self::fontFormatFromPath($font['url'] ?? '');
                }

                if (empty($font['weight'])) {
                    $font['weight'] = '400';
                }

                if (empty($font['family'])) {
                    $font['family'] = $font['name'] ?? $font['id'] ?? 'Custom Font';
                }

                $data['global_custom_fonts'][$index] = $font;
            }
        }

        foreach ($data as $key => $value) {
            // Override fields show the auto-generated palette value in the UI
            // for visibility. If the admin left it unchanged (equal to the
            // generated default), treat it as "no override" so we don't persist
            // a redundant override and palette changes keep flowing through.
            if (in_array($key, \App\Helpers\ColorPalette::OVERRIDE_KEYS, true)) {
                $auto = \App\Helpers\ColorPalette::defaultValueFor($key);
                if ($value === null || ($auto !== null && strtolower((string) $value) === strtolower((string) $auto))) {
                    $value = null;
                }
            }

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

        // Re-fill the form so the UI reflects the restored defaults. Override
        // fields are shown with their generated palette value (not null) for
        // visibility, while the stored state remains "no override".
        $resetOverrides = [];
        foreach (\App\Helpers\ColorPalette::OVERRIDE_KEYS as $key) {
            $resetOverrides[$key] = \App\Helpers\ColorPalette::defaultValueFor($key);
        }

        $this->form->fill(array_merge(
            $this->form->getState(),
            ['diu_color_palette' => 'diu', 'diu_primary_color' => null]
            + $resetOverrides
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
