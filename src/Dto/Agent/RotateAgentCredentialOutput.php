<?php

declare(strict_types=1);

namespace App\Dto\Agent;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\State\Processor\Agent\RotateAgentCredentialProcessor;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    shortName: 'RotateAgentCredential',
    operations: [
        new Post(
            uriTemplate: '/agent/credentials/rotate',
            security: "is_granted('ROLE_AGENT')",
            status: Response::HTTP_CREATED,
            input: false,
            read: false,
            processor: RotateAgentCredentialProcessor::class,
            openapi: new OpenApiOperation(
                tags: ['AgentCredential'],
                summary: 'Rotates the authenticated agent credential and returns the new bearer secret once.',
                security: [['AgentBearer' => []]],
            ),
        ),
    ],
)]
final readonly class RotateAgentCredentialOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $credentialId,
        public string $agentToken,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
