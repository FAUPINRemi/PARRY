<?php

namespace App\Service;

use OpenTelemetry\API\Globals;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\Contrib\Otlp\SpanExporter;

class OpenTelemetryService
{
    public static function init(): void
    {
        $endpoint = $_ENV['OTEL_EXPORTER_OTLP_ENDPOINT'] ?? 'https://api.uptrace.dev';
        $dsn = $_ENV['OTEL_EXPORTER_OTLP_HEADERS'] ?? '';
        $serviceName = $_ENV['OTEL_SERVICE_NAME'] ?? 'backend-parry';
        
        $resource = ResourceInfoFactory::emptyResource()->merge(
            ResourceInfo::create(Attributes::create([
                'service.name' => $serviceName,
                'service.version' => '1.0.0',
            ]))
        );
        
        $exporter = new SpanExporter(
            transport: (new \OpenTelemetry\Contrib\Otlp\HttpTransportFactory())->create(
                $endpoint . '/v1/traces',
                'application/json',
                ['uptrace-dsn' => $dsn]
            )
        );
        
        $tracerProvider = new TracerProvider(
            new SimpleSpanProcessor($exporter),
            null,
            $resource
        );
        
        Globals::registerTracerProvider($tracerProvider);
    }
}