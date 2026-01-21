<?php

namespace App\Service;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Psr\Log\LoggerInterface;

class TelemetryService
{
    private LoggerInterface $logger;
    private array $activeSpans = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Démarre un nouveau span (trace)
     */
    public function startSpan(string $name, array $attributes = [], string $kind = SpanKind::KIND_INTERNAL): SpanInterface
    {
        $tracer = Globals::tracerProvider()->getTracer('app');
        
        $spanBuilder = $tracer->spanBuilder($name)->setSpanKind($kind);
        
        foreach ($attributes as $key => $value) {
            $spanBuilder->setAttribute($key, $value);
        }
        
        $span = $spanBuilder->startSpan();
        
        $this->activeSpans[$name] = $span;
        
        $this->logger->info("Span started: {$name}", [
            'span_id' => $span->getContext()->getSpanId(),
            'trace_id' => $span->getContext()->getTraceId(),
        ]);
        
        return $span;
    }

    /**
     * Termine un span
     */
    public function endSpan(string $name): void
    {
        if (isset($this->activeSpans[$name])) {
            $this->activeSpans[$name]->end();
            $this->logger->info("Span ended: {$name}");
            unset($this->activeSpans[$name]);
        }
    }

    /**
     * Ajoute un event sur le span actif
     */
    public function addEvent(string $spanName, string $eventName, array $attributes = []): void
    {
        if (isset($this->activeSpans[$spanName])) {
            $this->activeSpans[$spanName]->addEvent($eventName, $attributes);
            $this->logger->debug("Event added: {$eventName} on span {$spanName}");
        }
    }

    /**
     * Enregistre une erreur sur un span
     */
    public function recordError(string $spanName, \Throwable $exception): void
    {
        if (isset($this->activeSpans[$spanName])) {
            $span = $this->activeSpans[$spanName];
            $span->recordException($exception, ['exception.escaped' => true]);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            
            $this->logger->error("Error recorded on span {$spanName}: {$exception->getMessage()}", [
                'exception' => $exception,
                'span_id' => $span->getContext()->getSpanId(),
                'trace_id' => $span->getContext()->getTraceId(),
            ]);
        }
    }

    /**
     * Wrapper pour exécuter du code dans un span automatiquement
     */
    public function trace(string $name, callable $callback, array $attributes = [])
    {
        $span = $this->startSpan($name, $attributes);
        
        try {
            $result = $callback($span);
            $span->end();
            return $result;
        } catch (\Throwable $e) {
            $this->recordError($name, $e);
            $span->end();
            throw $e;
        }
    }

    /**
     * Log une requête API (entrante ou sortante)
     */
    public function logApiCall(string $method, string $endpoint, int $statusCode, float $duration, array $metadata = []): void
    {
        $span = $this->startSpan("API {$method} {$endpoint}", [
            'http.method' => $method,
            'http.route' => $endpoint,
            'http.status_code' => $statusCode,
            'http.duration_ms' => $duration,
        ], SpanKind::KIND_SERVER);

        foreach ($metadata as $key => $value) {
            $span->setAttribute($key, $value);
        }

        $this->logger->info("API Call: {$method} {$endpoint}", [
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'metadata' => $metadata,
        ]);

        $span->end();
    }

    /**
     * Log une requête externe (vers une API tierce)
     */
    public function logExternalApiCall(string $method, string $url, int $statusCode, float $duration): void
    {
        $span = $this->startSpan("External API {$method}", [
            'http.method' => $method,
            'http.url' => $url,
            'http.status_code' => $statusCode,
            'http.duration_ms' => $duration,
        ], SpanKind::KIND_CLIENT);

        $this->logger->info("External API Call: {$method} {$url}", [
            'status_code' => $statusCode,
            'duration_ms' => $duration,
        ]);

        $span->end();
    }

    /**
     * Log simple avec corrélation automatique
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }

    /**
     * Récupère l'URL de trace Uptrace pour debug
     */
    public function getTraceUrl(?string $spanName = null): ?string
    {
        $span = $spanName && isset($this->activeSpans[$spanName]) 
            ? $this->activeSpans[$spanName] 
            : ($this->activeSpans[array_key_first($this->activeSpans)] ?? null);

        if ($span) {
            $traceId = $span->getContext()->getTraceId();
            return "https://app.uptrace.dev/traces/{$traceId}";
        }

        return null;
    }
}