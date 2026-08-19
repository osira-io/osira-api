<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Metrics;

use App\Service\Metrics\VictoriaMetricsHttpClient;
use App\Service\Metrics\VictoriaMetricsInvalidResponseException;
use App\Service\Metrics\VictoriaMetricsTimeoutException;
use App\Service\Metrics\VictoriaMetricsUnavailableException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class VictoriaMetricsHttpClientTest extends TestCase
{
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
