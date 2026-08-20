<?php

declare(strict_types=1);

namespace App\State\Provider\Agent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Agent\AgentConfigOutput;
use App\Security\Agent\AuthenticatedAgent;
use App\Service\Agent\AgentConfigOutputFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @implements ProviderInterface<AgentConfigOutput> */
final readonly class AgentConfigProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private AgentConfigOutputFactory $outputFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgentConfigOutput
    {
        $agent = $this->security->getUser();
        if (!$agent instanceof AuthenticatedAgent) {
            throw new AccessDeniedHttpException('Agent authentication is required.');
        }

        return $this->outputFactory->create($agent);
    }
}
