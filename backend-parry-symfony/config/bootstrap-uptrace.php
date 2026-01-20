<?php

use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\API\Globals;

if (!getenv('OTEL_EXPORTER_OTLP_ENDPOINT')) {
    return;
}

$resource = ResourceInfo::create(Attributes::create([
    'service.name' => getenv('OTEL_SERVICE_NAME') ?: 'backend-parry',
    'service.version' => '1.0.0',
    'deployment.environment' => getenv('APP_ENV') ?: 'dev',
]));

$exporter = new SpanExporter(
    getenv('OTEL_EXPORTER_OTLP_ENDPOINT') . '/v1/traces',
    'application/x-protobuf',
    array_filter([
        'uptrace-dsn' => getenv('UPTRACE_DSN'),
    ])
);

$tracerProvider = (new TracerProviderBuilder())
    ->addSpanProcessor(new SimpleSpanProcessor($exporter))
    ->setResource($resource)
    ->build();

Globals::registerInitializer(function () use ($tracerProvider) {
    return $tracerProvider;
});