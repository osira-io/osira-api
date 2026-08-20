<?php

declare(strict_types=1);

namespace App\Dto\Agent;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Security\Rbac\PermissionCode;
use App\State\Processor\Agent\RevokeAgentCredentialProcessor;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    shortName: 'AgentCredential',
    operations: [
        new Post(
            uriTemplate: '/agent-credentials/{id}/revoke',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::AGENT_CREDENTIALS_REVOKE."')",
            status: Response::HTTP_OK,
            input: false,
            output: self::class,
            read: false,
            processor: RevokeAgentCredentialProcessor::class,
            openapi: new OpenApiOperation(
                tags: ['AgentCredential'],
                summary: 'Revokes one agent credential immediately.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
final readonly class AgentCredentialOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $agentId,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $revokedAt,
        public ?\DateTimeImmutable $expiresAt,
    ) {
    }
}
