<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure;

use App\Agent\Domain\Entity\Agent;
use App\Enrollment\Domain\Entity\EnrollmentToken;
use App\Node\Domain\Entity\Node;
use App\NodeGroup\Domain\Entity\NodeGroup;
use App\Rbac\Domain\Entity\Permission;
use App\Rbac\Domain\Entity\Role;
use App\User\Domain\Entity\User;

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

    /**
     * Internal allowlist of the DH Auditor storage table for each audited entity. These are the
     * only table names the SQL reader may interpolate as identifiers — never a user-supplied value.
     *
     * @var array<string, string>
     */
    public const array TABLES = [
        'User' => 'audit_users',
        'Role' => 'audit_roles',
        'Permission' => 'audit_permissions',
        'Node' => 'audit_nodes',
        'NodeGroup' => 'audit_node_groups',
        'Agent' => 'audit_agents',
        'EnrollmentToken' => 'audit_enrollment_tokens',
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

    /** @return array<string, string> */
    public static function tables(?string $name = null): array
    {
        if (null === $name) {
            return self::TABLES;
        }

        $table = self::TABLES[$name] ?? null;
        if (null === $table) {
            throw new \InvalidArgumentException(\sprintf('Unknown audited entity "%s".', $name));
        }

        return [$name => $table];
    }

    private function __construct()
    {
    }
}
