<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Sla;

use App\Entity\Sla\SlaPeriodType;
use App\Service\Shared\Exception\ResourceValidationException;
use App\Service\Sla\SlaPeriodResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class SlaPeriodResolverTest extends TestCase
{
    public function testAutomaticAndCustomPeriodsAreDeterministic(): void
    {
        $resolver = new SlaPeriodResolver(new MockClock('2026-08-26T12:34:56+00:00'));

        $rolling = $resolver->resolve(SlaPeriodType::ROLLING_24_HOURS);
        self::assertSame('2026-08-25T12:34:56+00:00', $rolling->from->format(\DATE_ATOM));
        self::assertSame('2026-08-26T12:34:56+00:00', $rolling->to->format(\DATE_ATOM));

        $month = $resolver->resolve(SlaPeriodType::CURRENT_MONTH);
        self::assertSame('2026-08-01T00:00:00+00:00', $month->from->format(\DATE_ATOM));

        $custom = $resolver->resolve(SlaPeriodType::ROLLING_7_DAYS, '2026-08-20T00:00:00+00:00', '2026-08-21T00:00:00+00:00');
        self::assertSame(86400, $custom->seconds());
    }

    public function testCustomPeriodRequiresBothOrderedBounds(): void
    {
        $resolver = new SlaPeriodResolver(new MockClock('2026-08-26T12:00:00+00:00'));

        $this->expectException(ResourceValidationException::class);
        $resolver->resolve(SlaPeriodType::ROLLING_7_DAYS, '2026-08-20T00:00:00+00:00', null);
    }
}
