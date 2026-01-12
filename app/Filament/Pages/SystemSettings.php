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

        $this->form->fill($settings);
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
                    ])->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->success()
            ->title('Settings saved successfully')
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
