<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds the site branding / identity settings so the frontend shows the
 * current Daffodil values out of the box. Idempotent: re-running updates
 * existing rows without duplicating them.
 */
class BrandingSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Branding
            'branding_site_name'            => ['value' => 'Daffodil International University', 'type' => 'string'],
            'branding_site_short_name'      => ['value' => 'DAFFODIL', 'type' => 'string'],
            'branding_short_name'           => ['value' => 'DIU', 'type' => 'string'],
            'branding_badge_text'           => ['value' => 'Directory', 'type' => 'string'],
            'branding_tagline'              => ['value' => 'International University, Bangladesh', 'type' => 'string'],
            'branding_logo_mode'            => ['value' => 'text', 'type' => 'string'],
            'branding_logo_image'           => ['value' => '', 'type' => 'string'],
            'branding_monogram'             => ['value' => 'D', 'type' => 'string'],

            // Location
            'branding_address_header'       => ['value' => 'Smart City campus: Ashulia, Savar, Dhaka', 'type' => 'string'],
            'branding_address_footer'       => ['value' => 'Smart City Campus, Dhaka', 'type' => 'string'],
            'branding_address_full'         => ['value' => 'Daffodil Smart City, Ashulia, Dhaka', 'type' => 'string'],

            // Contact & links
            'branding_email'                => ['value' => 'info@daffodilvarsity.edu.bd', 'type' => 'string'],
            'branding_phone'               => ['value' => '', 'type' => 'string'],
            'branding_main_site_url'        => ['value' => 'https://daffodilvarsity.edu.bd', 'type' => 'string'],
            'branding_main_site_label'      => ['value' => 'Main Site', 'type' => 'string'],
            'branding_login_label'          => ['value' => 'Teacher Login', 'type' => 'string'],

            // Header labels
            'branding_portal_label'         => ['value' => 'Academic Portal', 'type' => 'string'],
            'branding_portal_sublabel'      => ['value' => 'Active Directory', 'type' => 'string'],
            'branding_scholars_label'       => ['value' => 'Scholars', 'type' => 'string'],
            'branding_stat_faculties_label' => ['value' => 'Academic Faculties', 'type' => 'string'],
            'branding_stat_departments_label' => ['value' => 'Departments', 'type' => 'string'],
            'branding_stat_profiles_label'  => ['value' => 'Faculty Profiles', 'type' => 'string'],

            // Footer
            'branding_footer_name'          => ['value' => 'DAFFODIL INTERNATIONAL UNIVERSITY', 'type' => 'string'],
            'branding_footer_descriptor'    => ['value' => 'Official Scholar Profile & Citation Directory', 'type' => 'string'],
            'branding_footer_copyright'     => ['value' => 'Daffodil International University', 'type' => 'string'],
            'branding_footer_accreditation' => ['value' => 'BAETE & IEB Accredited Smart Campus, Savar, Dhaka, Bangladesh.', 'type' => 'string'],

            // Meta / SEO
            'branding_meta_title_suffix'    => ['value' => ' - Faculty Directory', 'type' => 'string'],
            'branding_meta_description'      => ['value' => 'Daffodil International University — Faculty & Scholar Directory', 'type' => 'string'],
            'branding_watermark_text'        => ['value' => 'DIU', 'type' => 'string'],

            // Social links (empty by default; admin populates in settings)
            'branding_social_links'         => ['value' => '[]', 'type' => 'json'],
        ];

        $sort = 0;
        foreach ($rows as $key => $data) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'group'      => 'branding',
                    'value'      => $data['value'],
                    'type'       => $data['type'],
                    'label'      => ucwords(str_replace('_', ' ', substr($key, strlen('branding_')))),
                    'is_public'  => true,
                    'sort_order' => $sort++,
                ]
            );
        }
    }
}
