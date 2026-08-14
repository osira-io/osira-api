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
        $authorization = $this->authorization($user);

        return $authorization['isSuperAdmin'] || \in_array($permission, $authorization['permissions'], true);
    }

    /** @return list<string> */
    public function effectivePermissions(User $user): array
    {
        return $this->authorization($user)['permissions'];
    }

    public function reset(): void
    {
        $this->cache = [];
    }

    /** @return array{isSuperAdmin: bool, permissions: list<string>} */
    private function authorization(User $user): array
    {
        $id = (string) $user->id();
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $authorization = $this->users->authorizationData($user);
        if ($authorization['isSuperAdmin']) {
            $authorization['permissions'] = array_keys(PermissionCode::catalog());
        }
        sort($authorization['permissions'], \SORT_STRING);

        return $this->cache[$id] = $authorization;
    }
}
