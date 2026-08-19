<?php

declare(strict_types=1);

namespace App\Dto\Enrollment;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\Processor\Enrollment\AgentEnrollmentProcessor;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    shortName: 'AgentEnrollment',
    operations: [
        new Post(
            uriTemplate: '/agents/enroll',
            status: Response::HTTP_CREATED,
            input: AgentEnrollmentInput::class,
            output: self::class,
            read: false,
            processor: AgentEnrollmentProcessor::class,
        ),
    ],
)]
final readonly class AgentEnrollmentOutput
{
    public function __construct(
        public string $nodeId,
        #[ApiProperty(identifier: true)]
        public string $agentId,
        public string $agentToken,
    ) {
    }
}
