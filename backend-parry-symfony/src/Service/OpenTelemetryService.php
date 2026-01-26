<?php

namespace App\Service;

use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\ContentTypes;

class OpenTelemetryService
{
    private static ?TracerProvider $tracerProvider = null;

    public static function init(): void
    {
        $endpoint = $_ENV['OTEL_EXPORTER_OTLP_ENDPOINT'];
        $headers = [
            'uptrace-dsn' => $_ENV['UPTRACE_DSN']
        ];

        $resource = ResourceInfoFactory::emptyResource()->merge(
            ResourceInfo::create(Attributes::create([
                'service.name' => $_ENV['OTEL_SERVICE_NAME'] ?? 'backend-parry',
                'service.version' => $_ENV['OTEL_SERVICE_VERSION'] ?? '1.0.0',
                'deployment.environment' => $_ENV['APP_ENV'] ?? 'dev',
            ]))
        );

        $transport = (new OtlpHttpTransportFactory())->create(
            $endpoint,
            ContentTypes::PROTOBUF,
            $headers
        );

        $exporter = new SpanExporter($transport);
        $spanProcessor = new SimpleSpanProcessor($exporter);

        self::$tracerProvider = new TracerProvider(
            $spanProcessor,
            null,
            $resource
        );

        Sdk::builder()
            ->setTracerProvider(self::$tracerProvider)
            ->buildAndRegisterGlobal();

        echo "OpenTelemetry ok\n";
    }
}