<?php

// Bootstrap for unit tests.

// The root autoloader is registered last so that it takes precedence (Composer prepends)
// and the sources from `src` are tested, not the copy in `site`.
require "site/vendor/autoload.php";
require "vendor/autoload.php";

$moduleAutoload = __DIR__ . '/src/files/custom/Espo/Modules/PublicHolidays/vendor/autoload.php';

if (file_exists($moduleAutoload)) {
    require $moduleAutoload;
}
