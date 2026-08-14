<?php

declare(strict_types=1);

namespace App\Audit\Presentation\Api\Resource;

final readonly class AuditActorOutput
{
    public function __construct(public ?string $id, public ?string $email)
    {
    }
}
