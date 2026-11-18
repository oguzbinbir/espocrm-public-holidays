<?php

namespace Espo\Modules\PublicHolidays\Tools;

use RuntimeException;

class UnsupportedRegion extends RuntimeException
{
    public static function create(string $region): self
    {
        return new self("Public holiday region '$region' is not supported.");
    }
}
