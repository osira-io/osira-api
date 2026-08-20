<?php

declare(strict_types=1);

namespace App\State\Processor\Agent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agent\AgentCredentialOutput;
use App\Service\Agent\AgentCredentialManager;

/** @implements ProcessorInterface<object, AgentCredentialOutput> */
final readonly class RevokeAgentCredentialProcessor implements ProcessorInterface
{
    public function __construct(private AgentCredentialManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgentCredentialOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->manager->revoke(\is_string($id) ? $id : '');
    }
}
