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
    public const string MONITORING_TEMPLATES_READ = 'monitoring_templates.read';
    public const string MONITORING_TEMPLATES_CREATE = 'monitoring_templates.create';
    public const string MONITORING_TEMPLATES_UPDATE = 'monitoring_templates.update';
    public const string MONITORING_TEMPLATES_DELETE = 'monitoring_templates.delete';
    public const string ITEM_DEFINITIONS_READ = 'item_definitions.read';
    public const string ITEM_DEFINITIONS_CREATE = 'item_definitions.create';
    public const string ITEM_DEFINITIONS_UPDATE = 'item_definitions.update';
    public const string ITEM_DEFINITIONS_DELETE = 'item_definitions.delete';
    public const string METRICS_READ = 'metrics.read';
    public const string AGENT_CREDENTIALS_REVOKE = 'agent_credentials.revoke';

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
            self::MONITORING_TEMPLATES_READ => self::entry('Read monitoring templates', 'View monitoring templates and their assignments.', 'Monitoring'),
            self::MONITORING_TEMPLATES_CREATE => self::entry('Create monitoring templates', 'Create monitoring templates.', 'Monitoring'),
            self::MONITORING_TEMPLATES_UPDATE => self::entry('Update monitoring templates', 'Update monitoring templates and their item assignments.', 'Monitoring'),
            self::MONITORING_TEMPLATES_DELETE => self::entry('Delete monitoring templates', 'Delete non-system monitoring templates.', 'Monitoring'),
            self::ITEM_DEFINITIONS_READ => self::entry('Read item definitions', 'View item definitions.', 'Monitoring'),
            self::ITEM_DEFINITIONS_CREATE => self::entry('Create item definitions', 'Create item definitions.', 'Monitoring'),
            self::ITEM_DEFINITIONS_UPDATE => self::entry('Update item definitions', 'Update item definitions.', 'Monitoring'),
            self::ITEM_DEFINITIONS_DELETE => self::entry('Delete item definitions', 'Delete non-system item definitions.', 'Monitoring'),
            self::METRICS_READ => self::entry('Read metrics', 'Read VictoriaMetrics-backed metric values through the Osira API.', 'Monitoring'),
            self::AGENT_CREDENTIALS_REVOKE => self::entry('Revoke agent credentials', 'Revoke active control-plane agent credentials.', 'Enrollment'),
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
