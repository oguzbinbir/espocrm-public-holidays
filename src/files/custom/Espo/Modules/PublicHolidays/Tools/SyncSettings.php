<?php

namespace Espo\Modules\PublicHolidays\Tools;

use Espo\Core\Utils\Config;

/**
 * Global settings (Administration > Public Holidays).
 */
readonly class SyncSettings
{
    public const DEFAULT_LEAD_YEARS = 1;
    public const MAX_LEAD_YEARS = 5;

    public function __construct(
        public int $leadYears = self::DEFAULT_LEAD_YEARS,
        public bool $onlyActiveUsers = true,
    ) {}

    public static function fromConfig(Config $config): self
    {
        $leadYears = $config->get('phLeadYears');
        $onlyActiveUsers = $config->get('phOnlyActiveUsers');

        $leadYears = is_numeric($leadYears) ? (int) $leadYears : self::DEFAULT_LEAD_YEARS;
        $leadYears = max(0, min(self::MAX_LEAD_YEARS, $leadYears));

        return new self(
            leadYears: $leadYears,
            onlyActiveUsers: $onlyActiveUsers === null ? true : (bool) $onlyActiveUsers,
        );
    }
}
