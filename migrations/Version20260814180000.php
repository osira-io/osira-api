<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add database-backed RBAC, seed the Osira catalog, and migrate existing users.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        $this->addSql('CREATE TABLE permissions (id UUID NOT NULL, code VARCHAR(128) NOT NULL, name VARCHAR(128) NOT NULL, description TEXT DEFAULT NULL, category VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_permissions_code ON permissions (code)');
        $this->addSql('CREATE TABLE roles (id UUID NOT NULL, name VARCHAR(128) NOT NULL, slug VARCHAR(128) NOT NULL, description TEXT DEFAULT NULL, is_system BOOLEAN DEFAULT FALSE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_roles_name ON roles (name)');
        $this->addSql('CREATE UNIQUE INDEX uniq_roles_slug ON roles (slug)');
        $this->addSql('CREATE TABLE role_permissions (role_id UUID NOT NULL, permission_id UUID NOT NULL, PRIMARY KEY (role_id, permission_id))');
        $this->addSql('CREATE INDEX IDX_1FBA94E6FED90CCA ON role_permissions (permission_id)');
        $this->addSql('CREATE TABLE user_roles (user_id UUID NOT NULL, role_id UUID NOT NULL, PRIMARY KEY (user_id, role_id))');
        $this->addSql('CREATE INDEX IDX_54FCD59FD60322AC ON user_roles (role_id)');
        $this->addSql('ALTER TABLE role_permissions ADD CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE role_permissions ADD CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE NOT DEFERRABLE');

        $permissions = [
            ['00000000-0000-0000-0000-000000000001', 'users.read', 'Read users', 'View users and their assigned roles.', 'Users'],
            ['00000000-0000-0000-0000-000000000002', 'users.create', 'Create users', 'Create user accounts and assign roles.', 'Users'],
            ['00000000-0000-0000-0000-000000000003', 'users.update', 'Update users', 'Update user accounts and role assignments.', 'Users'],
            ['00000000-0000-0000-0000-000000000004', 'users.delete', 'Delete users', 'Delete user accounts.', 'Users'],
            ['00000000-0000-0000-0000-000000000005', 'roles.read', 'Read roles', 'View roles and their permissions.', 'Access control'],
            ['00000000-0000-0000-0000-000000000006', 'roles.create', 'Create roles', 'Create custom roles.', 'Access control'],
            ['00000000-0000-0000-0000-000000000007', 'roles.update', 'Update roles', 'Update roles and permission assignments.', 'Access control'],
            ['00000000-0000-0000-0000-000000000008', 'roles.delete', 'Delete roles', 'Delete custom roles.', 'Access control'],
            ['00000000-0000-0000-0000-000000000009', 'permissions.read', 'Read permissions', 'View the Osira permission catalog.', 'Access control'],
            ['00000000-0000-0000-0000-000000000010', 'nodes.read', 'Read nodes', 'View enrolled nodes.', 'Nodes'],
            ['00000000-0000-0000-0000-000000000011', 'nodes.update', 'Update nodes', 'Update node business properties and groups.', 'Nodes'],
            ['00000000-0000-0000-0000-000000000012', 'node_groups.read', 'Read node groups', 'View node groups.', 'Node groups'],
            ['00000000-0000-0000-0000-000000000013', 'node_groups.create', 'Create node groups', 'Create node groups.', 'Node groups'],
            ['00000000-0000-0000-0000-000000000014', 'node_groups.update', 'Update node groups', 'Update node groups.', 'Node groups'],
            ['00000000-0000-0000-0000-000000000015', 'node_groups.delete', 'Delete node groups', 'Delete node groups.', 'Node groups'],
            ['00000000-0000-0000-0000-000000000016', 'enrollment_tokens.create', 'Create enrollment tokens', 'Issue one-time agent enrollment tokens.', 'Enrollment'],
        ];
        foreach ($permissions as [$id, $code, $name, $description, $category]) {
            $this->addSql('INSERT INTO permissions (id, code, name, description, category, created_at, updated_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [$id, $code, $name, $description, $category]);
        }

        $roles = [
            ['00000000-0000-0000-0000-000000000101', 'Super Admin', 'super-admin', 'Protected system role with unrestricted access to Osira.'],
            ['00000000-0000-0000-0000-000000000102', 'Admin', 'admin', 'Administers users, roles, nodes, groups, and enrollment.'],
            ['00000000-0000-0000-0000-000000000103', 'Operator', 'operator', 'Operates nodes and manages node groups.'],
            ['00000000-0000-0000-0000-000000000104', 'Viewer', 'viewer', 'Reads nodes and node groups without modifying them.'],
        ];
        foreach ($roles as [$id, $name, $slug, $description]) {
            $this->addSql('INSERT INTO roles (id, name, slug, description, is_system, created_at, updated_at) VALUES (?, ?, ?, ?, TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [$id, $name, $slug, $description]);
        }

        $this->addSql("INSERT INTO role_permissions (role_id, permission_id) SELECT '00000000-0000-0000-0000-000000000101', id FROM permissions");
        $this->addSql("INSERT INTO role_permissions (role_id, permission_id) SELECT '00000000-0000-0000-0000-000000000102', id FROM permissions");
        $this->addSql("INSERT INTO role_permissions (role_id, permission_id) SELECT '00000000-0000-0000-0000-000000000103', id FROM permissions WHERE code IN ('nodes.read', 'nodes.update', 'node_groups.read', 'node_groups.create', 'node_groups.update', 'node_groups.delete')");
        $this->addSql("INSERT INTO role_permissions (role_id, permission_id) SELECT '00000000-0000-0000-0000-000000000104', id FROM permissions WHERE code IN ('nodes.read', 'node_groups.read')");

        $this->addSql("INSERT INTO user_roles (user_id, role_id) SELECT id, '00000000-0000-0000-0000-000000000101' FROM users WHERE roles::jsonb @> '[\"ROLE_ADMIN\"]'::jsonb");
        $this->addSql("INSERT INTO user_roles (user_id, role_id) SELECT id, '00000000-0000-0000-0000-000000000104' FROM users WHERE NOT (roles::jsonb @> '[\"ROLE_ADMIN\"]'::jsonb)");
        $this->addSql("UPDATE users SET roles = '[\"ROLE_USER\"]'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');

        $this->addSql("UPDATE users SET roles = '[\"ROLE_ADMIN\",\"ROLE_USER\"]' WHERE id IN (SELECT user_id FROM user_roles WHERE role_id = '00000000-0000-0000-0000-000000000101')");
        $this->addSql('DROP TABLE user_roles');
        $this->addSql('DROP TABLE role_permissions');
        $this->addSql('DROP TABLE roles');
        $this->addSql('DROP TABLE permissions');
    }
}
