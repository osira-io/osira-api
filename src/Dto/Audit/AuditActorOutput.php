<?php

declare(strict_types=1);

namespace App\Dto\Audit;

final readonly class AuditActorOutput
{
    public function __construct(public ?string $id, public ?string $email)
    {
    }
}
