<?php

declare(strict_types=1);

namespace App\Service\Sla;

final readonly class SlaPeriod
{
    public function __construct(public \DateTimeImmutable $from, public \DateTimeImmutable $to)
    {
    }

    public function seconds(): int
    {
        return $this->to->getTimestamp() - $this->from->getTimestamp();
    }
}
