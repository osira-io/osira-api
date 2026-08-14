<?php

declare(strict_types=1);

namespace App\Security\Rbac;

final class PermissionCode
{
    public const string USERS_READ = 'users.read';
    public const string USERS_CREATE = 'users.create';
    public const string USERS_UPDATE = 'users.update';
    public const string USERS_DELETE = 'users.delete';
    public const string ROLES_READ = 'roles.read';
    public const string ROLES_CREATE = 'roles.create';
    public const string ROLES_UPDATE = 'roles.update';
    public const string ROLES_DELETE = 'roles.delete';
    public const string PERMISSIONS_READ = 'permissions.read';
    public const string NODES_READ = 'nodes.read';
    public const string NODES_UPDATE = 'nodes.update';
    public const string NODE_GROUPS_READ = 'node_groups.read';
    public const string NODE_GROUPS_CREATE = 'node_groups.create';
    public const string NODE_GROUPS_UPDATE = 'node_groups.update';
    public const string NODE_GROUPS_DELETE = 'node_groups.delete';
    public const string ENROLLMENT_TOKENS_CREATE = 'enrollment_tokens.create';
    public const string AUDIT_LOGS_READ = 'audit_logs.read';

    /** @return array<string, array{name: string, description: string, category: string}> */
    public static function catalog(): array
    {
        return [
            self::USERS_READ => self::entry('Read users', 'View users and their assigned roles.', 'Users'),
            self::USERS_CREATE => self::entry('Create users', 'Create user accounts and assign roles.', 'Users'),
            self::USERS_UPDATE => self::entry('Update users', 'Update user accounts and role assignments.', 'Users'),
            self::USERS_DELETE => self::entry('Delete users', 'Delete user accounts.', 'Users'),
            self::ROLES_READ => self::entry('Read roles', 'View roles and their permissions.', 'Access control'),
            self::ROLES_CREATE => self::entry('Create roles', 'Create custom roles.', 'Access control'),
            self::ROLES_UPDATE => self::entry('Update roles', 'Update roles and permission assignments.', 'Access control'),
            self::ROLES_DELETE => self::entry('Delete roles', 'Delete custom roles.', 'Access control'),
            self::PERMISSIONS_READ => self::entry('Read permissions', 'View the Osira permission catalog.', 'Access control'),
            self::NODES_READ => self::entry('Read nodes', 'View enrolled nodes.', 'Nodes'),
            self::NODES_UPDATE => self::entry('Update nodes', 'Update node business properties and groups.', 'Nodes'),
            self::NODE_GROUPS_READ => self::entry('Read node groups', 'View node groups.', 'Node groups'),
            self::NODE_GROUPS_CREATE => self::entry('Create node groups', 'Create node groups.', 'Node groups'),
            self::NODE_GROUPS_UPDATE => self::entry('Update node groups', 'Update node groups.', 'Node groups'),
            self::NODE_GROUPS_DELETE => self::entry('Delete node groups', 'Delete node groups.', 'Node groups'),
            self::ENROLLMENT_TOKENS_CREATE => self::entry('Create enrollment tokens', 'Issue one-time agent enrollment tokens.', 'Enrollment'),
            self::AUDIT_LOGS_READ => self::entry('View audit logs', 'Allows viewing the audit history of Osira resources.', 'Audit'),
        ];
    }

    /** @return array{name: string, description: string, category: string} */
    private static function entry(string $name, string $description, string $category): array
    {
        return ['name' => $name, 'description' => $description, 'category' => $category];
    }

    private function __construct()
    {
    }
}
