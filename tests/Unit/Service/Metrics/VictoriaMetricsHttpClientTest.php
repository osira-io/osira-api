<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Metrics;

use App\Service\Metrics\VictoriaMetricsHttpClient;
use App\Service\Metrics\VictoriaMetricsInvalidResponseException;
use App\Service\Metrics\VictoriaMetricsTimeoutException;
use App\Service\Metrics\VictoriaMetricsUnavailableException;
use App\Service\Metrics\VictoriaMetricsWriteSample;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class VictoriaMetricsHttpClientTest extends TestCase
{
    public function testImportsSamplesInOneVictoriaMetricsRequest(): void
    {
        $captured = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, $options];

            return new MockResponse('', ['http_code' => 204]);
        });
        $client = new VictoriaMetricsHttpClient($httpClient, 'http://victoriametrics:8428', 5.0);

        $client->importSamples([
            new VictoriaMetricsWriteSample('node-1', 'custom.cpu', '42.5', new \DateTimeImmutable('2026-08-27T10:00:00.123+00:00')),
            new VictoriaMetricsWriteSample('node-1', 'custom.ready', '1', new \DateTimeImmutable('2026-08-27T10:00:01+00:00')),
        ]);

        self::assertIsArray($captured);
        self::assertSame('POST', $captured[0]);
        self::assertSame('http://victoriametrics:8428/api/v1/import', $captured[1]);
        $body = $captured[2]['body'] ?? null;
        self::assertIsString($body);
        $lines = explode("\n", trim($body));
        self::assertCount(2, $lines);
        self::assertSame([
            'metric' => ['__name__' => 'osira_item_value', 'node_id' => 'node-1', 'item_key' => 'custom.cpu'],
            'values' => [42.5],
            'timestamps' => [1787824800123],
        ], json_decode($lines[0], true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testInstantQueryReturnsNormalizedVectorRows(): void
    {
        $client = new VictoriaMetricsHttpClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'status' => 'success',
                    'data' => [
                        'resultType' => 'vector',
                        'result' => [[
                            'metric' => ['__name__' => 'osira_system_cpu_usage', 'node_id' => '01K2Z6QQN7Z7M0DYXJ5F0M0JVE', 'device' => 'cpu0'],
                            'value' => [1755604800.0, '42.5'],
                        ]],
                    ],
                ], \JSON_THROW_ON_ERROR)),
            ]),
            'http://victoriametrics:8428',
            5.0,
        );

        $rows = $client->instantQuery('osira_system_cpu_usage{node_id="01K2Z6QQN7Z7M0DYXJ5F0M0JVE"}');

        self::assertCount(1, $rows);
        self::assertSame('42.5', $rows[0]->value);
        self::assertSame(['__name__' => 'osira_system_cpu_usage', 'node_id' => '01K2Z6QQN7Z7M0DYXJ5F0M0JVE', 'device' => 'cpu0'], $rows[0]->labels);
    }

    public function testRangeQueryReturnsNormalizedSeriesRows(): void
    {
        $client = new VictoriaMetricsHttpClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'status' => 'success',
                    'data' => [
                        'resultType' => 'matrix',
                        'result' => [[
                            'metric' => ['__name__' => 'osira_system_disk_usage', 'node_id' => '01K2Z6QQN7Z7M0DYXJ5F0M0JVE', 'device' => 'nvme0n1p1'],
                            'values' => [
                                [1755604800.0, '77.1'],
                                [1755604860.0, '77.4'],
                            ],
                        ]],
                    ],
                ], \JSON_THROW_ON_ERROR)),
            ]),
            'http://victoriametrics:8428',
            5.0,
        );

        $rows = $client->rangeQuery(
            'osira_system_disk_usage{node_id="01K2Z6QQN7Z7M0DYXJ5F0M0JVE",device="nvme0n1p1"}',
            new \DateTimeImmutable('2026-08-19T12:00:00+00:00'),
            new \DateTimeImmutable('2026-08-19T12:10:00+00:00'),
            60,
        );

        self::assertCount(1, $rows);
        self::assertCount(2, $rows[0]->points);
        self::assertSame('77.4', $rows[0]->points[1]->value);
    }

    public function testReturnsEmptyResults(): void
    {
        $client = new VictoriaMetricsHttpClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'status' => 'success',
                    'data' => ['resultType' => 'vector', 'result' => []],
                ], \JSON_THROW_ON_ERROR)),
            ]),
            'http://victoriametrics:8428',
            5.0,
        );

        self::assertSame([], $client->instantQuery('osira_system_cpu_usage{node_id="node-1"}'));
    }

    public function testRejectsInvalidVictoriaMetricsResponses(): void
    {
        $client = new VictoriaMetricsHttpClient(
            new MockHttpClient([
                new MockResponse('{"status":"success","data":{"resultType":"vector"}}'),
            ]),
            'http://victoriametrics:8428',
            5.0,
        );

        $this->expectException(VictoriaMetricsInvalidResponseException::class);
        $client->instantQuery('osira_system_cpu_usage{node_id="node-1"}');
    }

    public function testConvertsTimeoutTransportErrors(): void
    {
        $client = new VictoriaMetricsHttpClient(
            new MockHttpClient([
                new MockResponse(info: ['error' => new TransportException('Idle timeout reached for "http://victoriametrics:8428/api/v1/query".')]),
            ]),
            'http://victoriametrics:8428',
            5.0,
        );

        $this->expectException(VictoriaMetricsTimeoutException::class);
        $client->instantQuery('osira_system_cpu_usage{node_id="node-1"}');
    }

    public function testConvertsUnavailableTransportErrors(): void
    {
        $client = new VictoriaMetricsHttpClient(
            new MockHttpClient([
                new MockResponse(info: ['error' => new TransportException('Connection refused')]),
            ]),
            'http://victoriametrics:8428',
            5.0,
        );

        $this->expectException(VictoriaMetricsUnavailableException::class);
        $client->instantQuery('osira_system_cpu_usage{node_id="node-1"}');
    }
}
