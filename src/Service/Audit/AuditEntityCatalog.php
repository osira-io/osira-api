<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\Agent\Agent;
use App\Entity\Agent\AgentCredential;
use App\Entity\Enrollment\EnrollmentToken;
use App\Entity\Incident\Incident;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Entity\Rbac\Permission;
use App\Entity\Rbac\Role;
use App\Entity\User\User;

final class AuditEntityCatalog
{
    public const array NAMES = ['User', 'Role', 'Permission', 'Node', 'NodeGroup', 'MonitoringTemplate', 'ItemDefinition', 'Agent', 'AgentCredential', 'EnrollmentToken', 'Incident'];

    /** @var array<string, class-string> */
    public const array ENTITIES = [
        'User' => User::class,
        'Role' => Role::class,
        'Permission' => Permission::class,
        'Node' => Node::class,
        'NodeGroup' => NodeGroup::class,
        'MonitoringTemplate' => MonitoringTemplate::class,
        'ItemDefinition' => ItemDefinition::class,
        'Agent' => Agent::class,
        'AgentCredential' => AgentCredential::class,
        'EnrollmentToken' => EnrollmentToken::class,
        'Incident' => Incident::class,
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
        'MonitoringTemplate' => 'audit_monitoring_templates',
        'ItemDefinition' => 'audit_item_definitions',
        'Agent' => 'audit_agents',
        'AgentCredential' => 'audit_agent_credentials',
        'EnrollmentToken' => 'audit_enrollment_tokens',
        'Incident' => 'audit_incidents',
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
