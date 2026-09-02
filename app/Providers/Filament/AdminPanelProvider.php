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
            /*
             * Without this, a missing policy method is not an error — Filament
             * falls through to allowing the action. That is how bulk delete went
             * ungoverned across 35 resources: Shield's method list omitted
             * 'deleteAny', so no policy defined it, and the check quietly passed
             * for anyone who could list a table.
             *
             * Strict mode turns that silence into a LogicException naming the
             * policy and method, so the next gap of this kind surfaces the first
             * time it is hit rather than years later.
             *
             * Verified against every role and the full page set before enabling;
             * behaviour is identical with it on and off. If a new resource ever
             * throws here, add the named method to its policy rather than
             * removing this line.
             */
            ->strictAuthorization()
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
            /*
             * Widgets are registered by hand, not discovered.
             *
             * discoverWidgets() put every file in app/Filament/Widgets onto the
             * dashboard. That is how page-scoped widgets ended up duplicated
             * there, and why anything dropped into the folder appeared uninvited.
             * The reporting work needs a dashboard that holds only what it is
             * told to hold.
             *
             * Nothing was deleted. A widget left out of the lists below stays on
             * disk and simply stops rendering — uncomment its line to bring it
             * back. Note that a widget listed nowhere is also invisible to
             * Shield, so it drops out of the role permission form; the rows
             * already in the permissions table are untouched.
             */
            ->widgets([
//                AccountWidget::class,
//                FilamentInfoWidget::class,

                // Dashboard.
                \App\Filament\Widgets\TeacherOverview::class,
                \App\Filament\Widgets\PublicationOverview::class,
                \App\Filament\Widgets\SystemStatsOverview::class,
                \App\Filament\Widgets\SystemPackagesStatsWidget::class,
                \App\Filament\Widgets\QueueStatusWidget::class,

                /*
                 * Parked pending the new report engine. Each one is either
                 * superseded by a configurable report, duplicated by the two
                 * overview widgets above, or was never rendered by any page.
                 * Kept here so the decision to delete can be made later, one
                 * line at a time, rather than guessed at now.
                 */
                \App\Filament\Widgets\PublicationYearWidget::class,
//                \App\Filament\Widgets\PublicationTypeChart::class,
//                \App\Filament\Widgets\PublicationQuartileWidget::class,
//                \App\Filament\Widgets\PublicationGrantTypeWidget::class,
//                \App\Filament\Widgets\PublicationLinkageChart::class,
//                \App\Filament\Widgets\PublicationAuthorStatsWidget::class,
//                \App\Filament\Widgets\CollaborationDistributionChart::class,
//                \App\Filament\Widgets\PublicationStatsOverview::class,
//                \App\Filament\Widgets\SystemOverviewWidget::class,
                \App\Filament\Widgets\TeacherStatsOverview::class,
                \App\Filament\Widgets\TeacherProfileStatsWidget::class, //oky
                \App\Filament\Widgets\TeacherProfessionalInfoWidget::class, //ok
//                \App\Filament\Widgets\TeacherResearchStatsWidget::class,
//                \App\Filament\Widgets\TeacherQuickActionsWidget::class,
            ])
            /*
             * Rendered by one specific page's getHeaderWidgets()/getFooterWidgets(),
             * never on the dashboard. They still have to be registered as Livewire
             * components or the page fails on the first update; widgets() would
             * register them but would also return them to the dashboard, which is
             * the duplication being undone here.
             *
             * TeacherVerificationStatsWidget is deliberately absent: it lives under
             * Filament/Resources, and discoverResources() registers every Livewire
             * component it walks past, including that one.
             */
            ->livewireComponents([
                \App\Filament\Widgets\TeacherDashboardOverview::class,
                \App\Filament\Widgets\PublicationSourceStatsWidget::class,
                \App\Filament\Widgets\PublicationIncentiveStatsOverview::class,
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
            ->navigationGroups([
                'User Management',
                'Publications',
                'Approvals',
                'Academic Structure',
                'Academic Lookups',
                'General Lookups',
                'Settings',
            ])
            ->authMiddleware([
                Authenticate::class,
                // A teacher who arrived through an activation link is signed in
                // but has no password of their own yet. This keeps them on the
                // setup page until they choose one; a prompt alone would let
                // them navigate away and stay in that state indefinitely.
                \App\Http\Middleware\RequirePasswordSetup::class,
            ])
            ->userMenuItems([
                \Filament\Navigation\MenuItem::make()
                    ->label('View Site')
                    ->url(url('/'), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-globe-alt'),
            ])
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
                FilamentApexChartsPlugin::make(),

            ])
            /*
             * The first rule below hands page scrolling back once every modal has
             * closed.
             *
             * Tables that open their filters as a modal or slide-over — the
             * teachers table does, via FiltersLayout::Modal plus slideOver() on
             * the trigger — go through Alpine's x-trap.noscroll, which locks the
             * page by writing overflow:hidden and a scrollbar-width padding
             * straight onto <html>. Applying a filter re-renders the table through
             * Livewire, and the trap's cleanup does not survive that morph, so the
             * lock outlives the modal that set it and the page stops scrolling.
             *
             * A stylesheet !important outranks an inline style carrying no
             * !important of its own, so the rule releases the lock whenever
             * nothing is genuinely open.
             *
             * The guard has to be .fi-modal-open and nothing else. That class is
             * bound to the modal's isOpen state, so it is present only while a
             * modal is really open. .fi-modal-window, which an earlier version of
             * this rule also tested for, is in the DOM permanently — every closed
             * modal still has one — so naming it made :has() always match and left
             * the rule as dead code.
             */
            ->renderHook(
                'panels::head.end',
                fn (): string => '<style>
                    html:not(:has(.fi-modal-open)) {
                        overflow-y: auto !important;
                        padding-right: 0 !important;
                    }

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
        /*
         * A companion 'panels::body.end' script used to try the same repair from
         * JavaScript, clearing the inline overflow on every Livewire commit and
         * every click. It carried the same .fi-modal-window test as the rule
         * above, so its "is a modal open?" check was always true and it never
         * cleared anything — while still running a document-wide click listener.
         * The CSS rule covers the case on its own; `git log` has the script if it
         * is ever wanted back.
         */
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
