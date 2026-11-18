<?php

/**
 * Generates the options of the field `WorkingTimeCalendar.phHolidayRegion` from the
 * Yasumi providers and writes translated labels (en_US, de_DE).
 *
 * Run after updating Yasumi:
 *
 *     php scripts/generate-regions.php
 */

$moduleDir = __DIR__ . '/../src/files/custom/Espo/Modules/PublicHolidays';

require $moduleDir . '/vendor/autoload.php';

use Yasumi\Yasumi;

// Providers that are not geographic regions.
$skip = ['US-NYSE'];

// Proper names where splitting the provider class name is not good enough.
$names = [
    'de_DE' => [
        'DE-BW' => 'Baden-Württemberg', 'DE-BY' => 'Bayern', 'DE-BE' => 'Berlin',
        'DE-BB' => 'Brandenburg', 'DE-HB' => 'Bremen', 'DE-HH' => 'Hamburg', 'DE-HE' => 'Hessen',
        'DE-MV' => 'Mecklenburg-Vorpommern', 'DE-NI' => 'Niedersachsen',
        'DE-NW' => 'Nordrhein-Westfalen', 'DE-RP' => 'Rheinland-Pfalz', 'DE-SL' => 'Saarland',
        'DE-SN' => 'Sachsen', 'DE-ST' => 'Sachsen-Anhalt', 'DE-SH' => 'Schleswig-Holstein',
        'DE-TH' => 'Thüringen',
        'AT-1' => 'Burgenland', 'AT-2' => 'Kärnten', 'AT-3' => 'Niederösterreich',
        'AT-4' => 'Oberösterreich', 'AT-5' => 'Salzburg', 'AT-6' => 'Steiermark', 'AT-7' => 'Tirol',
        'AT-8' => 'Vorarlberg', 'AT-9' => 'Wien',
        'CH-AG' => 'Aargau', 'CH-AI' => 'Appenzell Innerrhoden', 'CH-AR' => 'Appenzell Ausserrhoden',
        'CH-BE' => 'Bern', 'CH-BL' => 'Basel-Landschaft', 'CH-BS' => 'Basel-Stadt',
        'CH-FR' => 'Freiburg', 'CH-GE' => 'Genf', 'CH-GL' => 'Glarus', 'CH-GR' => 'Graubünden',
        'CH-JU' => 'Jura', 'CH-LU' => 'Luzern', 'CH-NE' => 'Neuenburg', 'CH-NW' => 'Nidwalden',
        'CH-OW' => 'Obwalden', 'CH-SG' => 'St. Gallen', 'CH-SH' => 'Schaffhausen',
        'CH-SO' => 'Solothurn', 'CH-SZ' => 'Schwyz', 'CH-TG' => 'Thurgau', 'CH-TI' => 'Tessin',
        'CH-UR' => 'Uri', 'CH-VD' => 'Waadt', 'CH-VS' => 'Wallis', 'CH-ZG' => 'Zug',
        'CH-ZH' => 'Zürich',
        'FR-57' => 'Moselle', 'FR-67' => 'Bas-Rhin', 'FR-68' => 'Haut-Rhin',
        'GB-ENG' => 'England', 'GB-NIR' => 'Nordirland', 'GB-SCT' => 'Schottland', 'GB-WLS' => 'Wales',
    ],
    'en_US' => [
        'DE-BW' => 'Baden-Württemberg', 'DE-BY' => 'Bavaria', 'DE-MV' => 'Mecklenburg-Western Pomerania',
        'DE-NW' => 'North Rhine-Westphalia', 'DE-RP' => 'Rhineland-Palatinate',
        'DE-ST' => 'Saxony-Anhalt', 'DE-SH' => 'Schleswig-Holstein',
        'CH-BL' => 'Basel-Landschaft', 'CH-BS' => 'Basel-Stadt', 'CH-NE' => 'Neuchâtel',
        'CH-SG' => 'St. Gallen', 'CH-ZH' => 'Zürich',
        'FR-57' => 'Moselle', 'FR-67' => 'Bas-Rhin', 'FR-68' => 'Haut-Rhin',
    ],
];

$nationwide = [
    'de_DE' => 'landesweit',
    'en_US' => 'nationwide',
];

$splitCamelCase = fn (string $s): string =>
    trim(preg_replace('/(?<=[a-z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $s));

$providers = Yasumi::getProviders();
ksort($providers);

$options = [''];
$translations = ['de_DE' => [], 'en_US' => []];

foreach ($providers as $code => $provider) {
    if (in_array($code, $skip, true)) {
        continue;
    }

    $options[] = $code;

    $parts = explode('/', $provider);
    $countryCode = explode('-', $code)[0];

    foreach (array_keys($translations) as $locale) {
        $country = Locale::getDisplayRegion('-' . $countryCode, $locale) ?: $splitCamelCase($parts[0]);

        if (count($parts) === 1) {
            $translations[$locale][$code] = "$country ({$nationwide[$locale]})";

            continue;
        }

        $sub = $names[$locale][$code] ?? implode(' – ', array_map($splitCamelCase, array_slice($parts, 1)));

        $translations[$locale][$code] = "$country – $sub";
    }
}

$writeJson = function (string $file, array $data): void {
    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
};

$defsFile = $moduleDir . '/Resources/metadata/entityDefs/WorkingTimeCalendar.json';
$defs = json_decode(file_get_contents($defsFile), true);
$defs['fields']['phHolidayRegion']['options'] = $options;
$writeJson($defsFile, $defs);

foreach ($translations as $locale => $labels) {
    $file = $moduleDir . "/Resources/i18n/$locale/WorkingTimeCalendar.json";
    $data = json_decode(file_get_contents($file), true);
    $empty = $data['options']['phHolidayRegion'][''] ?? '';
    $data['options']['phHolidayRegion'] = ['' => $empty] + $labels;
    $writeJson($file, $data);
}

echo count($options) - 1 . " regions written.\n";
