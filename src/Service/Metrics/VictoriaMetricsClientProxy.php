<?php

declare(strict_types=1);

namespace App\Service\Metrics;

final class VictoriaMetricsClientProxy implements VictoriaMetricsClientInterface
{
    private static ?VictoriaMetricsClientInterface $override = null;

    public function __construct(private readonly VictoriaMetricsHttpClient $inner)
    {
    }

    public function useClient(VictoriaMetricsClientInterface $client): void
    {
        self::$override = $client;
    }

    public function reset(): void
    {
        self::$override = null;
    }

    public function instantQuery(string $query): array
    {
        return (self::$override ?? $this->inner)->instantQuery($query);
    }

    public function rangeQuery(string $query, \DateTimeImmutable $from, \DateTimeImmutable $to, int $stepSeconds): array
    {
        return (self::$override ?? $this->inner)->rangeQuery($query, $from, $to, $stepSeconds);
    }
}
