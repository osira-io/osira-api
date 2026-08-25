<?php

declare(strict_types=1);

namespace App\Security\Rbac;

final class SystemRole
{
    public const string SUPER_ADMIN = 'super-admin';
    public const string ADMIN = 'admin';
    public const string OPERATOR = 'operator';
    public const string VIEWER = 'viewer';

    /** @return array<string, array{name: string, description: string, permissions: list<string>}> */
    public static function catalog(): array
    {
        $all = array_keys(PermissionCode::catalog());

        return [
            self::SUPER_ADMIN => [
                'name' => 'Super Admin',
                'description' => 'Protected system role with unrestricted access to Osira.',
                'permissions' => $all,
            ],
            self::ADMIN => [
                'name' => 'Admin',
                'description' => 'Administers users, roles, nodes, groups, and enrollment.',
                'permissions' => $all,
            ],
            self::OPERATOR => [
                'name' => 'Operator',
                'description' => 'Operates nodes and manages node groups.',
                'permissions' => [
                    PermissionCode::NODES_READ,
                    PermissionCode::NODES_UPDATE,
                    PermissionCode::NODE_GROUPS_READ,
                    PermissionCode::NODE_GROUPS_CREATE,
                    PermissionCode::NODE_GROUPS_UPDATE,
                    PermissionCode::NODE_GROUPS_DELETE,
                    PermissionCode::METRICS_READ,
                    PermissionCode::INCIDENTS_READ,
                    PermissionCode::INCIDENTS_ACKNOWLEDGE,
                    PermissionCode::INCIDENTS_COMMENT,
                    PermissionCode::MAINTENANCE_WINDOWS_READ,
                    PermissionCode::MAINTENANCE_WINDOWS_CREATE,
                    PermissionCode::MAINTENANCE_WINDOWS_UPDATE,
                ],
            ],
            self::VIEWER => [
                'name' => 'Viewer',
                'description' => 'Reads nodes and node groups without modifying them.',
                'permissions' => [PermissionCode::NODES_READ, PermissionCode::NODE_GROUPS_READ, PermissionCode::METRICS_READ, PermissionCode::INCIDENTS_READ, PermissionCode::MAINTENANCE_WINDOWS_READ],
            ],
        ];
    }

    private function __construct()
    {
    }
}
