<?php

namespace tests\unit\Espo\Modules\PublicHolidays\Tools;

use Espo\Core\Utils\Config;
use Espo\Modules\PublicHolidays\Tools\SyncSettings;
use PHPUnit\Framework\TestCase;

class SyncSettingsTest extends TestCase
{
    /**
     * @param array<string, mixed> $values
     */
    private function createConfig(array $values): Config
    {
        $config = $this->createStub(Config::class);

        $config->method('get')->willReturnCallback(fn (string $name) => $values[$name] ?? null);

        return $config;
    }

    public function testDefaults(): void
    {
        $settings = SyncSettings::fromConfig($this->createConfig([]));

        $this->assertSame(1, $settings->leadYears);
        $this->assertTrue($settings->onlyActiveUsers);
    }

    public function testValues(): void
    {
        $settings = SyncSettings::fromConfig($this->createConfig([
            'phLeadYears' => 2,
            'phOnlyActiveUsers' => false,
        ]));

        $this->assertSame(2, $settings->leadYears);
        $this->assertFalse($settings->onlyActiveUsers);
    }

    public function testLeadYearsIsClamped(): void
    {
        $this->assertSame(0, SyncSettings::fromConfig($this->createConfig(['phLeadYears' => -3]))->leadYears);
        $this->assertSame(5, SyncSettings::fromConfig($this->createConfig(['phLeadYears' => 99]))->leadYears);
    }

    public function testLeadYearsZero(): void
    {
        $this->assertSame(0, SyncSettings::fromConfig($this->createConfig(['phLeadYears' => 0]))->leadYears);
    }
}
