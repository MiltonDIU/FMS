<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
// use Filament\Pages\Dashboard; // Replaced by custom Dashboard
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->colors([
                'primary' => Color::Amber,
            ])
            // ->sidebarWidth('14rem')  // More compact sidebar (default ~18rem)
            ->sidebarCollapsibleOnDesktop()  // User can collapse sidebar
            ->maxContentWidth('full')
            ->resources([
                \App\Filament\Resources\Users\UserResource::class, // Users first
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
//                AccountWidget::class,
//                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
                FilamentApexChartsPlugin::make(),

            ])
            ->renderHook(
                'panels::head.end',
                fn (): string => '<style>
                    @media (max-width: 1024px) {
                        .responsive-vertical-tabs {
                            display: flex !important;
                            flex-direction: column !important;
                        }
                        .responsive-vertical-tabs nav[role="tablist"] {
                            flex-direction: row !important;
                            overflow-x: auto !important;
                            width: 100% !important;
                            border-right: none !important;
                            border-bottom: 1px solid #e5e7eb; /* gray-200 */
                            padding-bottom: 10px;
                            margin-bottom: 15px;
                        }
                        .responsive-vertical-tabs nav[role="tablist"] > * {
                             flex-shrink: 0 !important;
                        }
                    }
                </style>'
            );
    }
    public function boot(): void
    {
        \Filament\Tables\Table::configureUsing(function (\Filament\Tables\Table $table): void {
            $table
                ->paginationPageOptions([10, 20, 50, 100, 'all'])
                ->defaultPaginationPageOption(20);
        });
    }
}
