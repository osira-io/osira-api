<?php

declare(strict_types=1);

namespace App\Dto\Incident;

use ApiPlatform\Metadata\ApiProperty;

final readonly class IncidentActivityOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)] public string $id,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['opened', 'acknowledged', 'comment', 'resolved']])]
        public string $type,
        public ?string $message,
        public ?IncidentActorOutput $actor,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
