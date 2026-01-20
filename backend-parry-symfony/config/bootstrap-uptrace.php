<?php

declare(strict_types=1);

use Uptrace\Distro;

// Initialiser Uptrace avec le DSN
$dsn = $_ENV['UPTRACE_DSN'] ?? '';

if ($dsn) {
    Distro::builder()
        ->setDsn($dsn)
        ->setServiceName($_ENV['OTEL_SERVICE_NAME'] ?? 'backend-parry')
        ->setServiceVersion('1.0.0')
        ->setResourceAttributes([
            'deployment.environment' => $_ENV['APP_ENV'] ?? 'dev'
        ])
        ->buildAndRegisterGlobal();
}