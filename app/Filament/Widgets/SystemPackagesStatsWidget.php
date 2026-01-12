<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\Setting;

class SystemPackagesStatsWidget extends Widget
{
    protected  string $view = 'filament.widgets.system-packages-stats-widget';

    protected int | string | array $columnSpan = 1;
    protected static ?int $sort = 50;
    public static function canView(): bool
    {
        return auth()->user()->can('View:SystemPackagesStatsWidget');
    }
    public function getViewData(): array
    {
        $packages = [];
        $requiredPackages = [];

        // 1. Get required packages from composer.json
        try {
            $composerJson = File::get(base_path('composer.json'));
            $composerData = json_decode($composerJson, true);
            $requiredPackages = array_keys($composerData['require'] ?? []);
             // Also include dev packages if you want? usually just require is enough system stats.
             // $requiredPackages = array_merge($requiredPackages, array_keys($composerData['require-dev'] ?? []));
        } catch (\Exception $e) {
            // keep empty
        }

        // 2. Filter Installed Versions
        try {
            $installed = InstalledVersions::getAllRawData();
            $all = $installed[0]['versions'] ?? [];

            $checkUpdates = Setting::get('check_package_updates', false);

            foreach ($all as $name => $info) {
                // Only show packages present in composer.json "require"
                if (!in_array($name, $requiredPackages)) {
                    continue;
                }

                if (!isset($info['version'])) continue;

                $currentVersion = $info['pretty_version'] ?? $info['version'];
                // Clean version string (remove v prefix for comparison logic)
                $currentClean = ltrim($currentVersion, 'v');

                $latestVersion = null;
                $updateAvailable = false;

                if ($checkUpdates) {
                    $latestVersion = $this->getLatestVersion($name);
                    if ($latestVersion) {
                        // Compare
                        $updateAvailable = version_compare($currentClean, $latestVersion, '<');
                    }
                }

                $packages[] = [
                    'name' => $name,
                    'version' => $currentVersion,
                    'latest' => $latestVersion,
                    'update_available' => $updateAvailable,
                    'reference' => $info['reference'] ?? null,
                ];
            }

            // Sort by name
            usort($packages, fn($a, $b) => strcmp($a['name'], $b['name']));

        } catch (\Exception $e) {
            // Fallback
        }

        return [
            'packages' => $packages,
            'check_updates' => Setting::get('check_package_updates', false),
        ];
    }

    protected function getLatestVersion(string $package): ?string
    {
        // Cache for 12 hours like the other widget
        return Cache::remember("pkg_latest_{$package}", now()->addHours(12), function () use ($package) {
            try {
                // Using packagist API
                $response = Http::timeout(2)->get("https://repo.packagist.org/p2/{$package}.json");

                if ($response->successful()) {
                    $data = $response->json();
                    $versions = $data['packages'][$package] ?? [];

                    foreach ($versions as $versionData) {
                        $v = $versionData['version'];
                        if (!str_contains($v, 'dev') && !str_contains($v, 'RC') && !str_contains($v, 'beta')) {
                            return ltrim($v, 'v');
                        }
                    }
                    return isset($versions[0]['version']) ? ltrim($versions[0]['version'], 'v') : null;
                }
            } catch (\Exception $e) {
                return null;
            }
            return null;
        });
    }


}
