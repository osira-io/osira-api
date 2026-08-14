<?php

declare(strict_types=1);

namespace App\Audit;

use App\Entity\Agent;
use App\Entity\EnrollmentToken;
use App\Entity\Node;
use App\Entity\NodeGroup;
use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\User;

final class AuditEntityCatalog
{
    public const array NAMES = ['User', 'Role', 'Permission', 'Node', 'NodeGroup', 'Agent', 'EnrollmentToken'];

    /** @var array<string, class-string> */
    public const array ENTITIES = [
        'User' => User::class,
        'Role' => Role::class,
        'Permission' => Permission::class,
        'Node' => Node::class,
        'NodeGroup' => NodeGroup::class,
        'Agent' => Agent::class,
        'EnrollmentToken' => EnrollmentToken::class,
    ];

    /** @return array<string, class-string> */
    public static function entities(?string $name = null): array
    {
        if (null === $name) {
            return self::ENTITIES;
        }

        $class = self::ENTITIES[$name] ?? null;
        if (null === $class) {
            throw new \InvalidArgumentException(\sprintf('Unknown audited entity "%s".', $name));
        }

        return [$name => $class];
    }

    private function __construct()
    {
    }
}
