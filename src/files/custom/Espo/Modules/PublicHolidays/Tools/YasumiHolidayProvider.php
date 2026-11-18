<?php

namespace Espo\Modules\PublicHolidays\Tools;

use Espo\Core\Utils\Config;
use Yasumi\Holiday as YasumiHoliday;
use Yasumi\Yasumi;

/**
 * Calculates public holidays with the Yasumi library (https://www.yasumi.dev).
 *
 * Only holidays of the type "official" are returned. Observances such as
 * New Year's Eve or regionally restricted days (type "other") are skipped.
 */
class YasumiHolidayProvider implements HolidayProvider
{
    private const FALLBACK_LOCALE = 'en_US';

    /** @var ?array<string, string> */
    private ?array $providers = null;

    public function __construct(
        private Config $config,
    ) {}

    public function getHolidays(string $region, int $year): array
    {
        $provider = $this->getProviders()[$region] ?? null;

        if ($provider === null) {
            throw UnsupportedRegion::create($region);
        }

        $holidays = Yasumi::create($provider, $year, $this->getLocale());

        $list = [];

        foreach ($holidays->getHolidays() as $holiday) {
            if ($holiday->getType() !== YasumiHoliday::TYPE_OFFICIAL) {
                continue;
            }

            $list[] = new Holiday(
                region: $region,
                key: $holiday->getKey(),
                name: self::normalizeName($holiday->getName(), $holiday->getKey()),
                date: $holiday->format('Y-m-d'),
            );
        }

        usort($list, fn (Holiday $a, Holiday $b) => strcmp($a->date, $b->date));

        return $list;
    }

    /**
     * Yasumi returns the key if no translation exists (e.g. `germanUnityDay` in English).
     */
    private static function normalizeName(string $name, string $key): string
    {
        if ($name !== $key) {
            return $name;
        }

        return ucfirst((string) preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $key));
    }

    public function isSupported(string $region): bool
    {
        return array_key_exists($region, $this->getProviders());
    }

    /**
     * @return array<string, string> Region code => Yasumi provider.
     */
    private function getProviders(): array
    {
        $this->providers ??= Yasumi::getProviders();

        return $this->providers;
    }

    private function getLocale(): string
    {
        /** @var ?string $language */
        $language = $this->config->get('language');

        if (!$language) {
            return self::FALLBACK_LOCALE;
        }

        $available = Yasumi::getAvailableLocales();

        if (in_array($language, $available, true)) {
            return $language;
        }

        $prefix = explode('_', $language)[0];

        foreach ($available as $locale) {
            if (str_starts_with($locale, $prefix . '_')) {
                return $locale;
            }
        }

        return self::FALLBACK_LOCALE;
    }
}
