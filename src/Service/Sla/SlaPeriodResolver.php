<?php

declare(strict_types=1);

namespace App\Service\Sla;

use App\Entity\Sla\SlaPeriodType;
use App\Service\Shared\Exception\ResourceValidationException;
use Psr\Clock\ClockInterface;

final readonly class SlaPeriodResolver
{
    private const int MAX_CUSTOM_SECONDS = 31_622_400;

    public function __construct(private ClockInterface $clock)
    {
    }

    public function resolve(SlaPeriodType $type, ?string $from = null, ?string $to = null): SlaPeriod
    {
        if ((null === $from) !== (null === $to)) {
            throw new ResourceValidationException('from and to must be provided together.');
        }
        if (null !== $from && null !== $to) {
            $period = new SlaPeriod($this->parse($from, 'from'), $this->parse($to, 'to'));
            $this->validate($period);

            return $period;
        }

        $toDate = $this->utc($this->clock->now());
        $fromDate = match ($type) {
            SlaPeriodType::ROLLING_24_HOURS => $toDate->modify('-24 hours'),
            SlaPeriodType::ROLLING_7_DAYS => $toDate->modify('-7 days'),
            SlaPeriodType::ROLLING_30_DAYS => $toDate->modify('-30 days'),
            SlaPeriodType::CURRENT_MONTH => $toDate->modify('first day of this month')->setTime(0, 0),
        };

        return new SlaPeriod($fromDate, $toDate);
    }

    private function parse(string $value, string $field): \DateTimeImmutable
    {
        if (1 !== preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new ResourceValidationException($field.' must include an explicit timezone offset.');
        }
        try {
            return $this->utc(new \DateTimeImmutable($value));
        } catch (\Exception) {
            throw new ResourceValidationException($field.' must be a valid date-time.');
        }
    }

    private function validate(SlaPeriod $period): void
    {
        if ($period->to <= $period->from) {
            throw new ResourceValidationException('to must be after from.');
        }
        if ($period->seconds() > self::MAX_CUSTOM_SECONDS) {
            throw new ResourceValidationException('The custom SLA report period cannot exceed 366 days.');
        }
    }

    private function utc(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTimezone(new \DateTimeZone('UTC'));
    }
}
