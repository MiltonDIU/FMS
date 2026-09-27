<?php

namespace Database\Seeders;

use App\Helpers\MaintenanceMode;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Writes the System Settings → Maintenance Mode rows.
 *
 * MaintenanceMode falls back to the same values in code, so an unseeded
 * database behaves identically; the rows make the settings visible and typed
 * in the table like every other setting.
 *
 * Like InstitutionIdentitySeeder, this never overwrites a value that is already
 * there. A db:seed on a live installation must not quietly switch the site into
 * maintenance, or out of it in the middle of the work it was switched on for.
 */
class MaintenanceModeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            MaintenanceMode::ENABLED_KEY => [
                'value' => 'false',
                'type' => 'boolean',
                'label' => 'Maintenance Mode Enabled',
                'description' => 'While on, every public page shows only the maintenance message. The admin panel keeps working.',
            ],
            MaintenanceMode::ALLOW_ADMINS_KEY => [
                'value' => 'true',
                'type' => 'boolean',
                'label' => 'Allow Admins During Maintenance',
                'description' => 'Signed-in users with System Settings access browse the public site as usual while maintenance is on.',
            ],
            MaintenanceMode::TITLE_KEY => [
                'value' => MaintenanceMode::DEFAULT_TITLE,
                'type' => 'string',
                'label' => 'Maintenance Heading',
                'description' => 'Heading on the maintenance page. Empty uses the default.',
            ],
            MaintenanceMode::MESSAGE_KEY => [
                'value' => MaintenanceMode::DEFAULT_MESSAGE,
                'type' => 'string',
                'label' => 'Maintenance Message',
                'description' => 'Text on the maintenance page, also returned by the API. Empty uses the default.',
            ],
            MaintenanceMode::BACKGROUND_KEY => [
                'value' => '',
                'type' => 'string',
                'label' => 'Maintenance Background Image',
                'description' => 'Path on the public disk of the page background. Empty shows the plain page.',
            ],
        ];

        $sort = 0;

        foreach ($rows as $key => $row) {
            // Everything about the row except what it says: correcting the
            // metadata on an existing row is safe, correcting its value is not.
            $metadata = [
                'group' => 'maintenance',
                'type' => $row['type'],
                'label' => $row['label'],
                'description' => $row['description'],
                'is_public' => false,
                'sort_order' => $sort++,
            ];

            $existing = Setting::where('key', $key)->first();

            if ($existing) {
                $existing->update($metadata);
            } else {
                Setting::create($metadata + [
                    'key' => $key,
                    'value' => $row['value'],
                ]);
            }

            // Setting::get caches the whole row, type included.
            Cache::forget("setting.{$key}");
        }
    }
}
