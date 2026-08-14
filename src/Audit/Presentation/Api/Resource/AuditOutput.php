<?php

declare(strict_types=1);

namespace App\Audit\Presentation\Api\Resource;

final readonly class AuditOutput
{
    /** @param array<string, mixed> $changes */
    public function __construct(
        public string $id,
        public string $entity,
        public string $entityId,
        public string $action,
        public ?AuditActorOutput $actor,
        public ?string $ip,
        public ?string $securityContext,
        public \DateTimeImmutable $createdAt,
        public array $changes,
    ) {
    }
}
