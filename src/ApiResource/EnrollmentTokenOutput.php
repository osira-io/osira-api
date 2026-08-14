<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\CreateEnrollmentTokenInput;
use App\Security\PermissionCode;
use App\State\CreateEnrollmentTokenProcessor;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    shortName: 'EnrollmentToken',
    operations: [
        new Post(
            uriTemplate: '/enrollment-tokens',
            status: Response::HTTP_CREATED,
            security: "is_granted('".PermissionCode::ENROLLMENT_TOKENS_CREATE."')",
            input: CreateEnrollmentTokenInput::class,
            output: self::class,
            read: false,
            processor: CreateEnrollmentTokenProcessor::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class EnrollmentTokenOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $token,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
