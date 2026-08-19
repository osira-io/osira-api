<?php

declare(strict_types=1);

namespace App\Service\Metrics;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class VictoriaMetricsHttpClient implements VictoriaMetricsClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private float $timeoutSeconds,
    ) {
    }

    public function instantQuery(string $query): array
    {
        $payload = $this->request('/api/v1/query', ['query' => $query]);

        return $this->normalizeVector($payload);
    }

    public function rangeQuery(string $query, \DateTimeImmutable $from, \DateTimeImmutable $to, int $stepSeconds): array
    {
        $payload = $this->request('/api/v1/query_range', [
            'query' => $query,
            'start' => $from->format(\DATE_ATOM),
            'end' => $to->format(\DATE_ATOM),
            'step' => (string) $stepSeconds,
        ]);

        return $this->normalizeMatrix($payload);
    }

    /** @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function request(string $path, array $query): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/').$path, [
                'query' => $query,
                'timeout' => $this->timeoutSeconds,
            ]);
            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'timeout')) {
                throw new VictoriaMetricsTimeoutException('VictoriaMetrics request timed out.', 0, $exception);
            }

            throw new VictoriaMetricsUnavailableException('VictoriaMetrics is unavailable.', 0, $exception);
        } catch (\Throwable $exception) {
            throw new VictoriaMetricsInvalidResponseException('VictoriaMetrics returned an unreadable payload.', 0, $exception);
        }

        if ('success' !== ($payload['status'] ?? null)) {
            throw new VictoriaMetricsInvalidResponseException('VictoriaMetrics returned an invalid status.');
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload
     * @return list<VictoriaMetricsSample>
     */
    private function normalizeVector(array $payload): array
    {
        $data = $payload['data'] ?? null;
        $result = \is_array($data) ? ($data['result'] ?? null) : null;
        if (!\is_array($data) || 'vector' !== ($data['resultType'] ?? null) || !\is_array($result)) {
            throw new VictoriaMetricsInvalidResponseException('VictoriaMetrics vector response is invalid.');
        }

        $rows = [];
        foreach ($result as $row) {
            if (!\is_array($row) || !\is_array($row['metric'] ?? null) || !\is_array($row['value'] ?? null) || 2 !== \count($row['value'])) {
                throw new VictoriaMetricsInvalidResponseException('VictoriaMetrics vector row is invalid.');
            }
            /** @var array<string, mixed> $labels */
            $labels = $row['metric'];

            $rows[] = new VictoriaMetricsSample(
                self::normalizeLabels($labels),
                self::timestampFromValue($row['value'][0] ?? null),
                self::stringFromValue($row['value'][1] ?? null),
            );
        }

        return $rows;
    }

    /** @param array<string, mixed> $payload
     * @return list<VictoriaMetricsRangeSeries>
     */
    private function normalizeMatrix(array $payload): array
    {
        $data = $payload['data'] ?? null;
        $result = \is_array($data) ? ($data['result'] ?? null) : null;
        if (!\is_array($data) || 'matrix' !== ($data['resultType'] ?? null) || !\is_array($result)) {
            throw new VictoriaMetricsInvalidResponseException('VictoriaMetrics matrix response is invalid.');
        }

        $rows = [];
        foreach ($result as $row) {
            $values = \is_array($row) ? ($row['values'] ?? null) : null;
            if (!\is_array($row) || !\is_array($row['metric'] ?? null) || !\is_array($values)) {
                throw new VictoriaMetricsInvalidResponseException('VictoriaMetrics matrix row is invalid.');
            }
            /** @var array<string, mixed> $labels */
            $labels = $row['metric'];

            $points = [];
            foreach ($values as $value) {
                if (!\is_array($value) || 2 !== \count($value)) {
                    throw new VictoriaMetricsInvalidResponseException('VictoriaMetrics matrix point is invalid.');
                }
                $points[] = new VictoriaMetricsRangeSeriesPoint(
                    self::timestampFromValue($value[0] ?? null),
                    self::stringFromValue($value[1] ?? null),
                );
            }

            $rows[] = new VictoriaMetricsRangeSeries(self::normalizeLabels($labels), $points);
        }

        return $rows;
    }

    private static function timestampFromValue(mixed $value): \DateTimeImmutable
    {
        if (!is_numeric($value)) {
            throw new VictoriaMetricsInvalidResponseException('VictoriaMetrics timestamp is invalid.');
        }

        $timestamp = \DateTimeImmutable::createFromFormat('U.u', number_format((float) $value, 6, '.', ''));
        if (!$timestamp instanceof \DateTimeImmutable) {
            throw new VictoriaMetricsInvalidResponseException('VictoriaMetrics timestamp cannot be parsed.');
        }

        return $timestamp->setTimezone(new \DateTimeZone('UTC'));
    }

    private static function stringFromValue(mixed $value): string
    {
        if (!\is_string($value) && !is_numeric($value)) {
            throw new VictoriaMetricsInvalidResponseException('VictoriaMetrics value is invalid.');
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $labels
     * @return array<string, string>
     */
    private static function normalizeLabels(array $labels): array
    {
        $normalized = [];
        foreach ($labels as $key => $value) {
            if (\is_scalar($value)) {
                $normalized[$key] = (string) $value;
            }
        }

        return $normalized;
    }
}
