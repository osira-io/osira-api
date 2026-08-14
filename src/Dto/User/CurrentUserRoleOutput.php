<?php

declare(strict_types=1);

namespace App\Dto\User;

final readonly class CurrentUserRoleOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
    ) {
    }
}
