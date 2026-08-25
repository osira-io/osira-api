<?php

declare(strict_types=1);

namespace App\MessageHandler\Alert;

use App\Message\Alert\EvaluateAlerts;
use App\Service\Alert\AlertEvaluationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EvaluateAlertsHandler
{
    public function __construct(private AlertEvaluationService $evaluationService)
    {
    }

    public function __invoke(EvaluateAlerts $message): void
    {
        $this->evaluationService->evaluateAll();
    }
}
