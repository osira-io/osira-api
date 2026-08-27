<?php

declare(strict_types=1);

namespace App\Service\Alert;

use App\Repository\Node\NodeRepository;
use Psr\Clock\ClockInterface;

final readonly class AlertEvaluationService
{
    public function __construct(
        private NodeRepository $nodes,
        private AlertEvaluationManager $manager,
        private ClockInterface $clock,
    ) {
    }

    public function evaluateAll(): AlertEvaluationReport
    {
        $counts = ['nodes' => 0, 'rules' => 0, 'series' => 0, 'firing' => 0, 'ok' => 0, 'noData' => 0, 'errors' => 0];
        $evaluatedAt = $this->clock->now();

        foreach ($this->nodes->findAll() as $node) {
            $report = $this->manager->evaluateNode($node, $evaluatedAt);
            foreach ($counts as $name => $count) {
                $counts[$name] = $count + $report->{$name};
            }
        }

        return new AlertEvaluationReport(...$counts);
    }
}
