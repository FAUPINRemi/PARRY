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
        // Endpoint corrigé
        $endpoint = 'https://api.uptrace.dev/v1/traces';
        $headers = [
            'uptrace-dsn' => 'https://gPxh4wOmNX8jt-9fjWRBug@api.uptrace.dev?grpc=4317'
        ];

        // Créer les resource attributes
        $resource = ResourceInfoFactory::emptyResource()->merge(
            ResourceInfo::create(Attributes::create([
                'service.name' => 'backend-parry',
                'service.version' => '1.0.0',
                'deployment.environment' => 'dev',
            ]))
        );

        // Créer le transport HTTP
        $transport = (new OtlpHttpTransportFactory())->create(
            $endpoint,
            ContentTypes::PROTOBUF,
            $headers
        );

        // Créer l'exporter
        $exporter = new SpanExporter($transport);

        $spanProcessor = new SimpleSpanProcessor($exporter);

        // Créer le tracer provider
        self::$tracerProvider = new TracerProvider(
            $spanProcessor,
            null,
            $resource
        );

        // Enregistrer globalement via Sdk::builder
        Sdk::builder()
            ->setTracerProvider(self::$tracerProvider)
            ->buildAndRegisterGlobal();

        echo "OpenTelemetry initialized successfully\n";
    }
}