<?php

declare(strict_types=1);

namespace App\Service\Sla;

final readonly class TimeInterval
{
    public function __construct(public \DateTimeImmutable $from, public \DateTimeImmutable $to)
    {
        if ($to <= $from) {
            throw new \InvalidArgumentException('Interval end must be after start.');
        }
    }

    public function seconds(): int
    {
        return $this->to->getTimestamp() - $this->from->getTimestamp();
    }

    public function intersect(self $other): ?self
    {
        $from = $this->from > $other->from ? $this->from : $other->from;
        $to = $this->to < $other->to ? $this->to : $other->to;

        return $to > $from ? new self($from, $to) : null;
    }
}
