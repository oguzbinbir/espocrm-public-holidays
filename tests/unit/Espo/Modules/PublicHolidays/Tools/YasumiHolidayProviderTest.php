<?php

namespace tests\unit\Espo\Modules\PublicHolidays\Tools;

use Espo\Core\Utils\Config;
use Espo\Modules\PublicHolidays\Tools\Holiday;
use Espo\Modules\PublicHolidays\Tools\UnsupportedRegion;
use Espo\Modules\PublicHolidays\Tools\YasumiHolidayProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the holiday data against the law (§ 1 Feiertagsgesetz Baden-Württemberg),
 * so that a Yasumi update that changes the result is noticed.
 */
class YasumiHolidayProviderTest extends TestCase
{
    private function createProvider(?string $language = 'de_DE'): YasumiHolidayProvider
    {
        $config = $this->createStub(Config::class);

        $config->method('get')->willReturnCallback(
            fn (string $name) => $name === 'language' ? $language : null
        );

        return new YasumiHolidayProvider($config);
    }

    /**
     * @return array<string, string> date => key
     */
    private static function toMap(array $holidays): array
    {
        $map = [];

        foreach ($holidays as $holiday) {
            /** @var Holiday $holiday */
            $map[$holiday->date] = $holiday->key;
        }

        return $map;
    }

    /**
     * @return array<string, array{int, array<string, string>}>
     */
    public static function badenWurttembergProvider(): array
    {
        return [
            '2026' => [2026, [
                '2026-01-01' => 'newYearsDay',
                '2026-01-06' => 'epiphany',
                '2026-04-03' => 'goodFriday',
                '2026-04-06' => 'easterMonday',
                '2026-05-01' => 'internationalWorkersDay',
                '2026-05-14' => 'ascensionDay',
                '2026-05-25' => 'pentecostMonday',
                '2026-06-04' => 'corpusChristi',
                '2026-10-03' => 'germanUnityDay',
                '2026-11-01' => 'allSaintsDay',
                '2026-12-25' => 'christmasDay',
                '2026-12-26' => 'secondChristmasDay',
            ]],
            '2027' => [2027, [
                '2027-01-01' => 'newYearsDay',
                '2027-01-06' => 'epiphany',
                '2027-03-26' => 'goodFriday',
                '2027-03-29' => 'easterMonday',
                '2027-05-01' => 'internationalWorkersDay',
                '2027-05-06' => 'ascensionDay',
                '2027-05-17' => 'pentecostMonday',
                '2027-05-27' => 'corpusChristi',
                '2027-10-03' => 'germanUnityDay',
                '2027-11-01' => 'allSaintsDay',
                '2027-12-25' => 'christmasDay',
                '2027-12-26' => 'secondChristmasDay',
            ]],
            '2030' => [2030, [
                '2030-01-01' => 'newYearsDay',
                '2030-01-06' => 'epiphany',
                '2030-04-19' => 'goodFriday',
                '2030-04-22' => 'easterMonday',
                '2030-05-01' => 'internationalWorkersDay',
                '2030-05-30' => 'ascensionDay',
                '2030-06-10' => 'pentecostMonday',
                '2030-06-20' => 'corpusChristi',
                '2030-10-03' => 'germanUnityDay',
                '2030-11-01' => 'allSaintsDay',
                '2030-12-25' => 'christmasDay',
                '2030-12-26' => 'secondChristmasDay',
            ]],
        ];
    }

    /**
     * @param array<string, string> $expected
     */
    #[DataProvider('badenWurttembergProvider')]
    public function testBadenWurttemberg(int $year, array $expected): void
    {
        $holidays = $this->createProvider()->getHolidays('DE-BW', $year);

        $this->assertSame($expected, self::toMap($holidays));
    }

    public function testOnlyOfficialHolidays(): void
    {
        $keys = array_values(self::toMap($this->createProvider()->getHolidays('DE-BW', 2027)));

        // Observances are not public holidays in Baden-Württemberg.
        $this->assertNotContains('newYearsEve', $keys);
        $this->assertNotContains('christmasEve', $keys);
        $this->assertNotContains('pentecost', $keys);
        $this->assertNotContains('easter', $keys);
    }

    public function testBavariaAssumptionOfMaryIsNotOfficial(): void
    {
        $keys = array_values(self::toMap($this->createProvider()->getHolidays('DE-BY', 2027)));

        // Only in predominantly catholic municipalities, therefore type "other" in Yasumi.
        $this->assertNotContains('assumptionOfMary', $keys);
        $this->assertContains('epiphany', $keys);
    }

    public function testGermanNames(): void
    {
        $holidays = $this->createProvider('de_DE')->getHolidays('DE-BW', 2027);

        $names = array_map(fn (Holiday $h) => $h->name, $holidays);

        $this->assertContains('Neujahr', $names);
        $this->assertContains('Karfreitag', $names);
        $this->assertContains('Fronleichnam', $names);
        $this->assertContains('Tag der Deutschen Einheit', $names);
    }

    public function testEnglishFallbackForUnknownLanguage(): void
    {
        $holidays = $this->createProvider('xx_XX')->getHolidays('DE-BW', 2027);

        $names = array_map(fn (Holiday $h) => $h->name, $holidays);

        $this->assertContains('Good Friday', $names);
    }

    public function testMissingTranslationIsHumanized(): void
    {
        $holidays = $this->createProvider('en_US')->getHolidays('DE-BW', 2027);

        $names = array_map(fn (Holiday $h) => $h->name, $holidays);

        // Yasumi has no English name for this holiday and would return the key.
        $this->assertContains('German Unity Day', $names);
        $this->assertNotContains('germanUnityDay', $names);
    }

    public function testNoLanguage(): void
    {
        $holidays = $this->createProvider(null)->getHolidays('DE-BW', 2027);

        $this->assertCount(12, $holidays);
    }

    public function testSortedByDate(): void
    {
        $dates = array_keys(self::toMap($this->createProvider()->getHolidays('DE-BW', 2027)));

        $sorted = $dates;
        sort($sorted);

        $this->assertSame($sorted, $dates);
    }

    public function testRegionIsSetOnHoliday(): void
    {
        $holiday = $this->createProvider()->getHolidays('DE-BW', 2027)[0];

        $this->assertSame('DE-BW', $holiday->region);
        $this->assertSame('DE-BW:2027-01-01:newYearsDay', $holiday->getUniqueKey());
    }

    public function testIsSupported(): void
    {
        $provider = $this->createProvider();

        $this->assertTrue($provider->isSupported('DE-BW'));
        $this->assertTrue($provider->isSupported('DE'));
        $this->assertTrue($provider->isSupported('AT-9'));
        $this->assertFalse($provider->isSupported('DE-XX'));
        $this->assertFalse($provider->isSupported(''));
    }

    public function testUnsupportedRegionThrows(): void
    {
        $this->expectException(UnsupportedRegion::class);

        $this->createProvider()->getHolidays('DE-XX', 2027);
    }

    public function testAllRegionOptionsAreSupported(): void
    {
        $file = __DIR__ . '/../../../../../../src/files/custom/Espo/Modules/PublicHolidays/' .
            'Resources/metadata/entityDefs/WorkingTimeCalendar.json';

        $defs = json_decode(file_get_contents($file), true);
        $options = $defs['fields']['phHolidayRegion']['options'];

        $provider = $this->createProvider();

        foreach ($options as $region) {
            if ($region === '') {
                continue;
            }

            $this->assertTrue($provider->isSupported($region), "Region $region is not supported by Yasumi.");
        }
    }
}
