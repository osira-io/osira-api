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
    public const string ITEM_DEFINITIONS_MANAGE_COMMANDS = 'item_definitions.manage_commands';
    public const string METRICS_READ = 'metrics.read';
    public const string AGENT_CREDENTIALS_REVOKE = 'agent_credentials.revoke';
    public const string INCIDENTS_READ = 'incidents.read';
    public const string INCIDENTS_ACKNOWLEDGE = 'incidents.acknowledge';
    public const string INCIDENTS_COMMENT = 'incidents.comment';
    public const string MAINTENANCE_WINDOWS_READ = 'maintenance_windows.read';
    public const string MAINTENANCE_WINDOWS_CREATE = 'maintenance_windows.create';
    public const string MAINTENANCE_WINDOWS_UPDATE = 'maintenance_windows.update';
    public const string MAINTENANCE_WINDOWS_DELETE = 'maintenance_windows.delete';
    public const string NOTIFICATION_CHANNELS_READ = 'notification_channels.read';
    public const string NOTIFICATION_CHANNELS_CREATE = 'notification_channels.create';
    public const string NOTIFICATION_CHANNELS_UPDATE = 'notification_channels.update';
    public const string NOTIFICATION_CHANNELS_DELETE = 'notification_channels.delete';
    public const string NOTIFICATION_RULES_READ = 'notification_rules.read';
    public const string NOTIFICATION_RULES_CREATE = 'notification_rules.create';
    public const string NOTIFICATION_RULES_UPDATE = 'notification_rules.update';
    public const string NOTIFICATION_RULES_DELETE = 'notification_rules.delete';
    public const string SLAS_READ = 'slas.read';
    public const string SLAS_CREATE = 'slas.create';
    public const string SLAS_UPDATE = 'slas.update';
    public const string SLAS_DELETE = 'slas.delete';
    public const string ALERT_RULES_READ = 'alert_rules.read';
    public const string ALERT_RULES_CREATE = 'alert_rules.create';
    public const string ALERT_RULES_UPDATE = 'alert_rules.update';
    public const string ALERT_RULES_DELETE = 'alert_rules.delete';

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
            self::MONITORING_TEMPLATES_DELETE => self::entry('Delete monitoring templates', 'Delete monitoring templates.', 'Monitoring'),
            self::ITEM_DEFINITIONS_READ => self::entry('Read item definitions', 'View item definitions.', 'Monitoring'),
            self::ITEM_DEFINITIONS_CREATE => self::entry('Create item definitions', 'Create item definitions.', 'Monitoring'),
            self::ITEM_DEFINITIONS_UPDATE => self::entry('Update item definitions', 'Update item definitions.', 'Monitoring'),
            self::ITEM_DEFINITIONS_DELETE => self::entry('Delete item definitions', 'Delete item definitions.', 'Monitoring'),
            self::ITEM_DEFINITIONS_MANAGE_COMMANDS => self::entry('Manage item commands', 'Create or modify Bash and PowerShell collection commands.', 'Monitoring'),
            self::METRICS_READ => self::entry('Read metrics', 'Read VictoriaMetrics-backed metric values through the Osira API.', 'Monitoring'),
            self::AGENT_CREDENTIALS_REVOKE => self::entry('Revoke agent credentials', 'Revoke active control-plane agent credentials.', 'Enrollment'),
            self::INCIDENTS_READ => self::entry('Read incidents', 'View incidents produced by server-side alert evaluation.', 'Monitoring'),
            self::INCIDENTS_ACKNOWLEDGE => self::entry('Acknowledge incidents', 'Acknowledge firing incidents without changing their lifecycle status.', 'Monitoring'),
            self::INCIDENTS_COMMENT => self::entry('Comment on incidents', 'Add immutable comments to incidents.', 'Monitoring'),
            self::MAINTENANCE_WINDOWS_READ => self::entry('Read maintenance windows', 'View planned node maintenance windows.', 'Maintenance'),
            self::MAINTENANCE_WINDOWS_CREATE => self::entry('Create maintenance windows', 'Create planned node maintenance windows.', 'Maintenance'),
            self::MAINTENANCE_WINDOWS_UPDATE => self::entry('Update maintenance windows', 'Update planned node maintenance windows and target scopes.', 'Maintenance'),
            self::MAINTENANCE_WINDOWS_DELETE => self::entry('Delete maintenance windows', 'Delete planned node maintenance windows.', 'Maintenance'),
            self::NOTIFICATION_CHANNELS_READ => self::entry('Read notification channels', 'View notification channels without secret material.', 'Notifications'),
            self::NOTIFICATION_CHANNELS_CREATE => self::entry('Create notification channels', 'Create email and webhook notification channels.', 'Notifications'),
            self::NOTIFICATION_CHANNELS_UPDATE => self::entry('Update notification channels', 'Update email and webhook notification channels.', 'Notifications'),
            self::NOTIFICATION_CHANNELS_DELETE => self::entry('Delete notification channels', 'Delete notification channels.', 'Notifications'),
            self::NOTIFICATION_RULES_READ => self::entry('Read notification rules', 'View incident notification routing rules.', 'Notifications'),
            self::NOTIFICATION_RULES_CREATE => self::entry('Create notification rules', 'Create incident notification routing rules.', 'Notifications'),
            self::NOTIFICATION_RULES_UPDATE => self::entry('Update notification rules', 'Update incident notification routing rules.', 'Notifications'),
            self::NOTIFICATION_RULES_DELETE => self::entry('Delete notification rules', 'Delete incident notification routing rules.', 'Notifications'),
            self::SLAS_READ => self::entry('Read SLAs', 'View SLA configurations and calculated availability reports.', 'SLA'),
            self::SLAS_CREATE => self::entry('Create SLAs', 'Create SLA objectives and scopes.', 'SLA'),
            self::SLAS_UPDATE => self::entry('Update SLAs', 'Update SLA objectives and scopes.', 'SLA'),
            self::SLAS_DELETE => self::entry('Delete SLAs', 'Delete SLA configurations.', 'SLA'),
            self::ALERT_RULES_READ => self::entry('Read alert rules', 'View alert rules and their template, node group, and node assignments.', 'Monitoring'),
            self::ALERT_RULES_CREATE => self::entry('Create alert rules', 'Create alert rules and assign them to templates, node groups, or nodes.', 'Monitoring'),
            self::ALERT_RULES_UPDATE => self::entry('Update alert rules', 'Update alert rules and their assignments.', 'Monitoring'),
            self::ALERT_RULES_DELETE => self::entry('Delete alert rules', 'Delete alert rules.', 'Monitoring'),
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
