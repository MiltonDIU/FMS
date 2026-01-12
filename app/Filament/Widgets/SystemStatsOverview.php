<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Composer\InstalledVersions;

class SystemStatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = '10s'; // Auto-refresh every 10 seconds

    protected function getStats(): array
    {
        $stats = [];

        // CPU Usage
        $cpuUsage = $this->getCpuUsage();
        $stats[] = Stat::make('CPU Usage', $cpuUsage . '%')
            ->description($this->getCpuInfo())
            ->descriptionIcon('heroicon-m-cpu-chip')
            ->color($cpuUsage > 80 ? 'danger' : ($cpuUsage > 60 ? 'warning' : 'success'))
            ->chart($this->generateChartData($cpuUsage));

        // Memory Usage
        $memoryData = $this->getMemoryUsage();
        $stats[] = Stat::make('Memory Usage', $memoryData['percentage'] . '%')
            ->description($memoryData['used'] . ' / ' . $memoryData['total'])
            ->descriptionIcon('heroicon-m-circle-stack')
            ->color($memoryData['percentage'] > 80 ? 'danger' : ($memoryData['percentage'] > 60 ? 'warning' : 'success'))
            ->chart($this->generateChartData($memoryData['percentage']));

        // Disk Usage
        $diskData = $this->getDiskUsage();
        $stats[] = Stat::make('Disk Usage', $diskData['percentage'] . '%')
            ->description($diskData['used'] . ' / ' . $diskData['total'])
            ->descriptionIcon('heroicon-m-server-stack')
            ->color($diskData['percentage'] > 80 ? 'danger' : ($diskData['percentage'] > 60 ? 'warning' : 'success'))
            ->chart($this->generateChartData($diskData['percentage']));

        // Server Uptime
        $uptime = $this->getServerUptime();
        $stats[] = Stat::make('Server Uptime', $uptime['formatted'])
            ->description($uptime['description'])
            ->descriptionIcon('heroicon-m-clock')
            ->color('info');

        // Active Users (if you track sessions)
        $activeUsers = $this->getActiveUsers();
        $stats[] = Stat::make('Active Users', $activeUsers)
            ->description('Currently online')
            ->descriptionIcon('heroicon-m-users')
            ->color('primary');

        // PHP Version
        $phpVersion = PHP_VERSION;
        $stats[] = Stat::make('PHP Version', $phpVersion)
            ->description('Running on PHP')
            ->descriptionIcon('heroicon-m-code-bracket')
            ->color('gray');

        // Laravel Version
        $laravelStatus = $this->getPackageStatus('laravel/framework', 'Laravel');
        $stats[] = Stat::make('Laravel', $laravelStatus['current'])
            ->description($laravelStatus['message'])
            ->descriptionIcon('heroicon-m-command-line')
            ->color($laravelStatus['color']);

        // Filament Version
        $filamentStatus = $this->getPackageStatus('filament/filament', 'Filament');
        $stats[] = Stat::make('Filament', $filamentStatus['current'])
            ->description($filamentStatus['message'])
            ->descriptionIcon('heroicon-m-sparkles')
            ->color($filamentStatus['color']);

        return $stats;
    }

    protected function getCpuUsage(): float
    {
        if (stristr(PHP_OS, 'win')) {
            // Windows
            $wmi = new \COM("Winmgmts://");
            $cpus = $wmi->execquery("SELECT LoadPercentage FROM Win32_Processor");
            $cpuLoad = 0;
            $count = 0;
            foreach ($cpus as $cpu) {
                $cpuLoad += $cpu->LoadPercentage;
                $count++;
            }
            return $count > 0 ? round($cpuLoad / $count, 1) : 0;
        } else {
            // Linux/Unix
            $load = sys_getloadavg();
            return $load ? round($load[0] * 100 / $this->getCpuCores(), 1) : 0;
        }
    }

    protected function getCpuInfo(): string
    {
        $cores = $this->getCpuCores();
        return $cores . ' Core' . ($cores > 1 ? 's' : '');
    }

    protected function getCpuCores(): int
    {
        if (stristr(PHP_OS, 'win')) {
            $process = @popen('wmic cpu get NumberOfCores', 'rb');
            if (false !== $process) {
                fgets($process);
                $cores = (int) fgets($process);
                pclose($process);
                return $cores > 0 ? $cores : 1;
            }
        } else {
            $cores = (int) shell_exec('nproc 2>/dev/null || echo 1');
            return $cores > 0 ? $cores : 1;
        }
        return 1;
    }

    protected function getMemoryUsage(): array
    {
        if (stristr(PHP_OS, 'win')) {
            // Windows
            $output = shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value');
            $lines = explode("\n", trim($output));
            $data = [];
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $data[trim($key)] = trim($value);
                }
            }

            $total = isset($data['TotalVisibleMemorySize']) ? (int) $data['TotalVisibleMemorySize'] : 0;
            $free = isset($data['FreePhysicalMemory']) ? (int) $data['FreePhysicalMemory'] : 0;
            $used = $total - $free;

            return [
                'used' => $this->formatBytes($used * 1024),
                'total' => $this->formatBytes($total * 1024),
                'percentage' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
            ];
        } else {
            // Linux/Unix
            $meminfo = @file_get_contents('/proc/meminfo');
            if ($meminfo) {
                preg_match('/MemTotal:\s+(\d+)/', $meminfo, $totalMatch);
                preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $availableMatch);

                $total = isset($totalMatch[1]) ? (int) $totalMatch[1] : 0;
                $available = isset($availableMatch[1]) ? (int) $availableMatch[1] : 0;
                $used = $total - $available;

                return [
                    'used' => $this->formatBytes($used * 1024),
                    'total' => $this->formatBytes($total * 1024),
                    'percentage' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
                ];
            }
        }

        // Fallback
        $memoryLimit = ini_get('memory_limit');
        $memoryUsed = memory_get_usage(true);
        $memoryTotal = $this->convertToBytes($memoryLimit);

        return [
            'used' => $this->formatBytes($memoryUsed),
            'total' => $this->formatBytes($memoryTotal),
            'percentage' => $memoryTotal > 0 ? round(($memoryUsed / $memoryTotal) * 100, 1) : 0,
        ];
    }

    protected function getDiskUsage(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;

        return [
            'used' => $this->formatBytes($used),
            'total' => $this->formatBytes($total),
            'percentage' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
        ];
    }

    protected function getServerUptime(): array
    {
        if (stristr(PHP_OS, 'win')) {
            // Windows
            $uptime = shell_exec('net stats srv | find "Statistics since"');
            return [
                'formatted' => 'N/A',
                'description' => 'Uptime tracking unavailable on Windows',
            ];
        } else {
            // Linux/Unix
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                $seconds = (int) explode(' ', $uptime)[0];
                $days = floor($seconds / 86400);
                $hours = floor(($seconds % 86400) / 3600);
                $minutes = floor(($seconds % 3600) / 60);

                $formatted = '';
                if ($days > 0) $formatted .= $days . 'd ';
                if ($hours > 0) $formatted .= $hours . 'h ';
                $formatted .= $minutes . 'm';

                return [
                    'formatted' => trim($formatted),
                    'description' => 'System running smoothly',
                ];
            }
        }

        return [
            'formatted' => 'N/A',
            'description' => 'Unable to determine uptime',
        ];
    }

    protected function getActiveUsers(): int
    {
        // Option 1: Using Laravel's session
        return \DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->count();

        // Option 2: Using authenticated users cache
        // return \Cache::get('active_users_count', 0);
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    protected function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }

        return $value;
    }

    protected function generateChartData(float $currentValue): array
    {
        // Generate realistic chart data
        $data = [];
        for ($i = 0; $i < 7; $i++) {
            $data[] = max(0, min(100, $currentValue + rand(-10, 10)));
        }
        return $data;
    }

    protected function getPackageStatus(string $package, string $label): array
    {
        try {
            $current = InstalledVersions::getVersion($package);
            
            // Remove 'v' prefix if exists
            $current = ltrim($current, 'v');
        } catch (\Exception $e) {
            return [
                'current' => 'Unknown',
                'message' => 'Not installed',
                'color' => 'gray',
            ];
        }

        $latest = null;
        $checkUpdates = Setting::get('check_package_updates', false);

        if ($checkUpdates) {
             $latest = $this->getLatestVersion($package);
        }

        if ($latest) {
             $isOutdated = version_compare($current, $latest, '<');
             return [
                 'current' => 'v' . $current,
                 'message' => $isOutdated ? "Update available: v{$latest}" : 'Latest version',
                 'color' => $isOutdated ? 'warning' : 'success',
             ];
        }

        return [
            'current' => 'v' . $current,
            'message' => 'Installed',
            'color' => 'success',
        ];
    }

    protected function getLatestVersion(string $package): ?string
    {
        return Cache::remember("package_latest_version_{$package}", now()->addHours(12), function () use ($package) {
            try {
                $response = Http::timeout(2)->get("https://repo.packagist.org/p2/{$package}.json");
                
                if ($response->successful()) {
                    $data = $response->json();
                    $versions = $data['packages'][$package] ?? [];
                    
                    // Simple logic: get the first version that is a stable release (no -dev, -RC)
                    foreach ($versions as $versionData) {
                        $v = $versionData['version'];
                        // quick check for stable
                        if (!str_contains($v, 'dev') && !str_contains($v, 'RC') && !str_contains($v, 'beta')) {
                            return ltrim($v, 'v');
                        }
                    }
                    // Fallback to first available if no stable found
                    return isset($versions[0]['version']) ? ltrim($versions[0]['version'], 'v') : null;
                }
            } catch (\Exception $e) {
                // Fail silently
            }
            return null;
        });
    }

    public static function canView(): bool
    {
        return auth()->user()->can('View:SystemStatsOverview');
    }
}
