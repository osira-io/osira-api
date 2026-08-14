<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Resource;

final readonly class CurrentUserRoleOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
    ) {
    }
}
