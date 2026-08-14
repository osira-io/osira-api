<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\UpdateCurrentUserInput;
use App\State\CurrentUserProvider;
use App\State\UpdateCurrentUserProcessor;

#[ApiResource(
    shortName: 'CurrentUser',
    operations: [
        new Get(
            uriTemplate: '/me',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: CurrentUserProvider::class,
            openapi: new OpenApiOperation(
                tags: ['CurrentUser'],
                summary: 'Returns the authenticated user context and effective permissions.',
                security: [['JWT' => []]],
            ),
        ),
        new Patch(
            uriTemplate: '/me',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: UpdateCurrentUserInput::class,
            output: self::class,
            denormalizationContext: ['allow_extra_attributes' => false],
            read: false,
            processor: UpdateCurrentUserProcessor::class,
            openapi: new OpenApiOperation(
                tags: ['CurrentUser'],
                summary: 'Updates the authenticated user locale.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
final readonly class CurrentUserOutput
{
    /** @param list<CurrentUserRoleOutput> $roles
     * @param list<string> $permissions
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $email,
        public string $locale,
        public array $roles,
        public array $permissions,
    ) {
    }
}
