<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * Fills in each country's demonym, keyed by its ISO 3166-1 alpha-3 code.
 *
 * Keyed on the code rather than the name because names vary in spelling and
 * punctuation between lists, while the code does not.
 *
 * Only countries that are actually plausible for staff and their education
 * history are listed. The rest keep a null nationality and go on being matched
 * the way they were before, so nothing regresses — and any gap can be filled in
 * on the Countries screen without touching this file.
 *
 * Re-runnable: it only writes rows whose nationality is still empty, so a
 * value corrected by hand in the panel is never overwritten.
 */
class CountryNationalitySeeder extends Seeder
{
    public function run(): void
    {
        $written = 0;

        foreach (self::DEMONYMS as $code => $nationality) {
            $written += Country::query()
                ->where('code', $code)
                ->whereNull('nationality')
                ->update(['nationality' => $nationality]);
        }

        $this->command?->info("Nationality set on {$written} country row(s).");
    }

    /**
     * ISO 3166-1 alpha-3 → demonym.
     */
    protected const DEMONYMS = [
        // South Asia
        'BGD' => 'Bangladeshi',
        'IND' => 'Indian',
        'PAK' => 'Pakistani',
        'NPL' => 'Nepalese',
        'LKA' => 'Sri Lankan',
        'BTN' => 'Bhutanese',
        'MDV' => 'Maldivian',
        'AFG' => 'Afghan',

        // East and South-East Asia
        'CHN' => 'Chinese',
        'JPN' => 'Japanese',
        'KOR' => 'South Korean',
        'PRK' => 'North Korean',
        'TWN' => 'Taiwanese',
        'HKG' => 'Hong Konger',
        'MNG' => 'Mongolian',
        'MYS' => 'Malaysian',
        'SGP' => 'Singaporean',
        'IDN' => 'Indonesian',
        'THA' => 'Thai',
        'VNM' => 'Vietnamese',
        'PHL' => 'Filipino',
        'MMR' => 'Burmese',
        'KHM' => 'Cambodian',
        'LAO' => 'Laotian',
        'BRN' => 'Bruneian',

        // Middle East and Central Asia
        'SAU' => 'Saudi',
        'ARE' => 'Emirati',
        'QAT' => 'Qatari',
        'KWT' => 'Kuwaiti',
        'BHR' => 'Bahraini',
        'OMN' => 'Omani',
        'JOR' => 'Jordanian',
        'LBN' => 'Lebanese',
        'SYR' => 'Syrian',
        'IRQ' => 'Iraqi',
        'IRN' => 'Iranian',
        'ISR' => 'Israeli',
        'PSE' => 'Palestinian',
        'YEM' => 'Yemeni',
        'TUR' => 'Turkish',
        'KAZ' => 'Kazakh',
        'UZB' => 'Uzbek',
        'TKM' => 'Turkmen',
        'KGZ' => 'Kyrgyz',
        'TJK' => 'Tajik',
        'AZE' => 'Azerbaijani',
        'ARM' => 'Armenian',
        'GEO' => 'Georgian',

        // Europe
        'GBR' => 'British',
        'IRL' => 'Irish',
        'FRA' => 'French',
        'DEU' => 'German',
        'ITA' => 'Italian',
        'ESP' => 'Spanish',
        'PRT' => 'Portuguese',
        'NLD' => 'Dutch',
        'BEL' => 'Belgian',
        'LUX' => 'Luxembourgish',
        'CHE' => 'Swiss',
        'AUT' => 'Austrian',
        'SWE' => 'Swedish',
        'NOR' => 'Norwegian',
        'DNK' => 'Danish',
        'FIN' => 'Finnish',
        'ISL' => 'Icelandic',
        'POL' => 'Polish',
        'CZE' => 'Czech',
        'SVK' => 'Slovak',
        'HUN' => 'Hungarian',
        'ROU' => 'Romanian',
        'BGR' => 'Bulgarian',
        'GRC' => 'Greek',
        'HRV' => 'Croatian',
        'SRB' => 'Serbian',
        'SVN' => 'Slovenian',
        'BIH' => 'Bosnian',
        'ALB' => 'Albanian',
        'MKD' => 'Macedonian',
        'MNE' => 'Montenegrin',
        'UKR' => 'Ukrainian',
        'BLR' => 'Belarusian',
        'RUS' => 'Russian',
        'LTU' => 'Lithuanian',
        'LVA' => 'Latvian',
        'EST' => 'Estonian',
        'MDA' => 'Moldovan',
        'CYP' => 'Cypriot',
        'MLT' => 'Maltese',

        // Americas
        'USA' => 'American',
        'CAN' => 'Canadian',
        'MEX' => 'Mexican',
        'BRA' => 'Brazilian',
        'ARG' => 'Argentine',
        'CHL' => 'Chilean',
        'COL' => 'Colombian',
        'PER' => 'Peruvian',
        'VEN' => 'Venezuelan',
        'ECU' => 'Ecuadorian',
        'BOL' => 'Bolivian',
        'URY' => 'Uruguayan',
        'PRY' => 'Paraguayan',
        'CUB' => 'Cuban',
        'JAM' => 'Jamaican',
        'TTO' => 'Trinidadian',
        'DOM' => 'Dominican',
        'HTI' => 'Haitian',
        'CRI' => 'Costa Rican',
        'PAN' => 'Panamanian',
        'GTM' => 'Guatemalan',

        // Africa
        'EGY' => 'Egyptian',
        'LBY' => 'Libyan',
        'TUN' => 'Tunisian',
        'DZA' => 'Algerian',
        'MAR' => 'Moroccan',
        'SDN' => 'Sudanese',
        'NGA' => 'Nigerian',
        'GHA' => 'Ghanaian',
        'KEN' => 'Kenyan',
        'UGA' => 'Ugandan',
        'TZA' => 'Tanzanian',
        'ETH' => 'Ethiopian',
        'SOM' => 'Somali',
        'ZAF' => 'South African',
        'ZWE' => 'Zimbabwean',
        'ZMB' => 'Zambian',
        'MOZ' => 'Mozambican',
        'AGO' => 'Angolan',
        'CMR' => 'Cameroonian',
        'SEN' => 'Senegalese',
        'CIV' => 'Ivorian',
        'MLI' => 'Malian',
        'RWA' => 'Rwandan',
        'MWI' => 'Malawian',
        'MUS' => 'Mauritian',

        // Oceania
        'AUS' => 'Australian',
        'NZL' => 'New Zealander',
        'FJI' => 'Fijian',
        'PNG' => 'Papua New Guinean',
    ];
}
