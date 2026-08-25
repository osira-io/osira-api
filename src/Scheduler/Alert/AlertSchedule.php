<?php

declare(strict_types=1);

namespace App\Scheduler\Alert;

use App\Message\Alert\EvaluateAlerts;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('alerts')]
final readonly class AlertSchedule implements ScheduleProviderInterface
{
    public function __construct(private int $intervalSeconds)
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(RecurringMessage::every($this->intervalSeconds, new EvaluateAlerts()));
    }
}
