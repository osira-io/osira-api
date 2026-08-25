<?php

declare(strict_types=1);

namespace App\Command\Alert;

use App\Service\Alert\AlertEvaluationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'osira:alerts:evaluate', description: 'Evaluates effective node alert rules against VictoriaMetrics.')]
final class EvaluateAlertsCommand extends Command
{
    public function __construct(private readonly AlertEvaluationService $evaluationService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->evaluationService->evaluateAll();
        (new SymfonyStyle($input, $output))->success(\sprintf(
            'Evaluated %d rules on %d nodes (%d firing, %d ok, %d no data, %d errors).',
            $report->rules,
            $report->nodes,
            $report->firing,
            $report->ok,
            $report->noData,
            $report->errors,
        ));

        return 0 === $report->errors ? self::SUCCESS : self::FAILURE;
    }
}
