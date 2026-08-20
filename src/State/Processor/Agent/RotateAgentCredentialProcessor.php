<?php

declare(strict_types=1);

namespace App\State\Processor\Agent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agent\RotateAgentCredentialOutput;
use App\Security\Agent\AuthenticatedAgent;
use App\Service\Agent\AgentCredentialManager;
use App\Service\Agent\InvalidAgentCredential;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/** @implements ProcessorInterface<object, RotateAgentCredentialOutput> */
final readonly class RotateAgentCredentialProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private AgentCredentialManager $manager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RotateAgentCredentialOutput
    {
        $agent = $this->security->getUser();
        if (!$agent instanceof AuthenticatedAgent) {
            throw new AccessDeniedHttpException('Agent authentication is required.');
        }

        try {
            $issuedCredential = $this->manager->rotate($agent);
        } catch (InvalidAgentCredential $exception) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid agent credential.', $exception);
        }

        return new RotateAgentCredentialOutput(
            (string) $issuedCredential->credential->id(),
            $issuedCredential->rawToken,
            $issuedCredential->credential->createdAt(),
        );
    }
}
