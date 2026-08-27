<?php

declare(strict_types=1);

namespace App\State\Processor\Agent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agent\AgentMetricBatchInput;
use App\Dto\Agent\AgentMetricIngestionOutput;
use App\Security\Agent\AuthenticatedAgent;
use App\Service\Metrics\MetricIngestionManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @implements ProcessorInterface<AgentMetricBatchInput, AgentMetricIngestionOutput> */
final readonly class AgentMetricIngestionProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private MetricIngestionManager $manager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgentMetricIngestionOutput
    {
        $authenticatedAgent = $this->security->getUser();
        if (!$authenticatedAgent instanceof AuthenticatedAgent) {
            throw new AccessDeniedHttpException('Agent authentication is required.');
        }

        return new AgentMetricIngestionOutput($this->manager->ingest($authenticatedAgent->node(), $data->samples));
    }
}
