<?php

use App\Kernel;

// Charger Uptrace/OpenTelemetry
if (file_exists(dirname(__DIR__).'/config/bootstrap-uptrace.php')) {
    require_once dirname(__DIR__).'/config/bootstrap-uptrace.php';
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';



return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};


