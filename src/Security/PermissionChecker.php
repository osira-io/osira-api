<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Contracts\Service\ResetInterface;

final class PermissionChecker implements ResetInterface
{
    /** @var array<string, array{isSuperAdmin: bool, permissions: list<string>}> */
    private array $cache = [];

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function isGranted(User $user, string $permission): bool
    {
        $id = (string) $user->id();
        $authorization = $this->cache[$id] ??= $this->users->authorizationData($user);

        return $authorization['isSuperAdmin'] || \in_array($permission, $authorization['permissions'], true);
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
