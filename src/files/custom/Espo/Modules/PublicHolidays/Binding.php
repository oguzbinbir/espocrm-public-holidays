<?php

namespace Espo\Modules\PublicHolidays;

use Espo\Core\Binding\Binder;
use Espo\Core\Binding\BindingProcessor;
use Espo\Modules\PublicHolidays\Tools\HolidayProvider;
use Espo\Modules\PublicHolidays\Tools\YasumiHolidayProvider;

/**
 * @noinspection PhpUnused
 */
class Binding implements BindingProcessor
{
    public function process(Binder $binder): void
    {
        $binder->bindImplementation(HolidayProvider::class, YasumiHolidayProvider::class);
    }
}
