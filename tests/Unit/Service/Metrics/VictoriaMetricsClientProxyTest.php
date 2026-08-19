<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Metrics;

use App\Service\Metrics\VictoriaMetricsClientProxy;
use App\Service\Metrics\VictoriaMetricsHttpClient;
use App\Tests\Functional\Support\FakeVictoriaMetricsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class VictoriaMetricsClientProxyTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(VictoriaMetricsClientProxy::class);
        $property = $reflection->getProperty('override');
        $property->setValue(null, null);

        parent::tearDown();
    }

    public function testDelegatesToInnerClientByDefault(): void
    {
        $proxy = new VictoriaMetricsClientProxy(new VictoriaMetricsHttpClient(
            new MockHttpClient([
                new MockResponse('{"status":"success","data":{"resultType":"vector","result":[]}}'),
            ]),
            'http://victoriametrics:8428',
            5.0,
        ));

        self::assertSame([], $proxy->instantQuery('up'));
    }

    public function testCanOverrideAndResetClient(): void
    {
        $proxy = new VictoriaMetricsClientProxy(new VictoriaMetricsHttpClient(
            new MockHttpClient([
                new MockResponse('{"status":"success","data":{"resultType":"matrix","result":[]}}'),
            ]),
            'http://victoriametrics:8428',
            5.0,
        ));
        $override = new FakeVictoriaMetricsClient();
        $override->rangeResult = [];

        $proxy->useClient($override);
        self::assertSame([], $proxy->rangeQuery('override', new \DateTimeImmutable('2026-08-19T12:00:00+00:00'), new \DateTimeImmutable('2026-08-19T12:01:00+00:00'), 30));
        self::assertSame(['override|2026-08-19T12:00:00+00:00|2026-08-19T12:01:00+00:00|30'], $override->queries);

        $proxy->reset();
        self::assertSame([], $proxy->rangeQuery('inner', new \DateTimeImmutable('2026-08-19T12:02:00+00:00'), new \DateTimeImmutable('2026-08-19T12:03:00+00:00'), 60));
    }
}
