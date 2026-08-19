<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\NodeGroup\CreateNodeGroupInput;
use App\Dto\NodeGroup\UpdateNodeGroupInput;
use App\Dto\Rbac\CreateRoleInput;
use App\Dto\Rbac\UpdateRoleInput;
use App\Dto\User\CreateUserInput;
use App\Dto\User\UpdateUserInput;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Rbac\Role;
use App\Entity\User\User;
use App\Repository\Monitoring\MonitoringTemplateRepository;
use App\Repository\Rbac\RoleRepository;
use App\Security\Rbac\PermissionCode;
use App\Security\Rbac\SystemRole;
use App\Service\Monitoring\MonitoringCatalogSynchronizer;
use App\Service\NodeGroup\NodeGroupManager;
use App\Service\Rbac\RbacCatalogSynchronizer;
use App\Service\Rbac\RoleManager;
use App\Service\Shared\Exception\ResourceConflictException;
use App\Service\Shared\Exception\ResourceValidationException;
use App\Service\User\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ApplicationManagersTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserManager $userManager;
    private RoleManager $roleManager;
    private NodeGroupManager $nodeGroupManager;
    private RoleRepository $roleRepository;
    private MonitoringTemplateRepository $monitoringTemplateRepository;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        self::getContainer()->get(RbacCatalogSynchronizer::class)->synchronize();
        self::getContainer()->get(MonitoringCatalogSynchronizer::class)->synchronize();

        $this->userManager = self::getContainer()->get(UserManager::class);
        $this->roleManager = self::getContainer()->get(RoleManager::class);
        $this->nodeGroupManager = self::getContainer()->get(NodeGroupManager::class);
        $this->roleRepository = self::getContainer()->get(RoleRepository::class);
        $this->monitoringTemplateRepository = self::getContainer()->get(MonitoringTemplateRepository::class);
    }

    public function testUserManagerCreatesUpdatesAndDeletesUsersThroughTheFactoryPath(): void
    {
        $viewerRole = $this->roleRepository->findOneBy(['slug' => SystemRole::VIEWER]);
        self::assertInstanceOf(Role::class, $viewerRole);

        $createInput = new CreateUserInput();
        $createInput->email = '  Viewer@Example.com  ';
        $createInput->password = 'correct horse battery staple';
        $createInput->roleIds = [(string) $viewerRole->id()];

        $user = $this->userManager->create($createInput);

        self::assertSame('viewer@example.com', $user->getUserIdentifier());
        self::assertNotSame('correct horse battery staple', $user->getPassword());
        self::assertSame([SystemRole::VIEWER], array_map(static fn (Role $role): string => $role->slug(), $user->businessRoles()->toArray()));

        $updateInput = new UpdateUserInput();
        $updateInput->setEmail('updated@example.com');
        $updateInput->setRoleIds([(string) $viewerRole->id()]);

        $updatedUser = $this->userManager->update((string) $user->id(), $updateInput);
        self::assertSame('updated@example.com', $updatedUser->getUserIdentifier());

        $this->userManager->delete((string) $user->id());
        self::assertNull($this->entityManager->find(User::class, $user->id()));
    }

    public function testUserManagerRejectsNullPasswordAndDeletingLastSuperAdmin(): void
    {
        $superAdminRole = $this->roleRepository->findOneBy(['slug' => SystemRole::SUPER_ADMIN]);
        self::assertInstanceOf(Role::class, $superAdminRole);

        $createInput = new CreateUserInput();
        $createInput->email = 'root@example.com';
        $createInput->password = 'correct horse battery staple';
        $createInput->roleIds = [(string) $superAdminRole->id()];
        $user = $this->userManager->create($createInput);

        $updateInput = new UpdateUserInput();
        $updateInput->setPassword(null);

        try {
            $this->userManager->update((string) $user->id(), $updateInput);
            self::fail('Expected null passwords to be rejected.');
        } catch (ResourceValidationException $exception) {
            self::assertStringContainsString('password cannot be null', strtolower($exception->getMessage()));
        }

        $this->expectException(ResourceConflictException::class);
        $this->userManager->delete((string) $user->id());
    }

    public function testRoleManagerCreatesUpdatesAndDeletesCustomRoles(): void
    {
        $createInput = new CreateRoleInput();
        $createInput->name = '  Audit Team  ';
        $createInput->description = '  Read-only audit access  ';
        $createInput->permissionCodes = [PermissionCode::NODES_READ];

        $role = $this->roleManager->create($createInput);

        self::assertSame('Audit Team', $role->name());
        self::assertSame('audit-team', $role->slug());
        self::assertSame(['nodes.read'], array_map(static fn ($permission): string => $permission->code(), $role->permissions()->toArray()));

        $updateInput = new UpdateRoleInput();
        $updateInput->setDescription('Updated description');
        $updateInput->setPermissionCodes([PermissionCode::NODES_READ, PermissionCode::NODE_GROUPS_READ]);

        $updatedRole = $this->roleManager->update((string) $role->id(), $updateInput);
        self::assertSame('Updated description', $updatedRole->description());
        self::assertCount(2, $updatedRole->permissions());

        $this->roleManager->delete((string) $role->id());
        self::assertNull($this->entityManager->find(Role::class, $role->id()));
    }

    public function testRoleManagerRejectsUnknownPermissionsAndSystemRoleDeletion(): void
    {
        $createInput = new CreateRoleInput();
        $createInput->name = 'Broken Role';
        $createInput->permissionCodes = ['unknown.permission'];

        try {
            $this->roleManager->create($createInput);
            self::fail('Expected unknown permissions to be rejected.');
        } catch (ResourceValidationException $exception) {
            self::assertStringContainsString('unknown permission', strtolower($exception->getMessage()));
        }

        $systemRole = $this->roleRepository->findOneBy(['slug' => SystemRole::ADMIN]);
        self::assertInstanceOf(Role::class, $systemRole);

        $this->expectException(ResourceConflictException::class);
        $this->roleManager->delete((string) $systemRole->id());
    }

    public function testNodeGroupManagerCreatesUpdatesAndRejectsUnknownMonitoringTemplates(): void
    {
        $linuxBase = $this->monitoringTemplateRepository->findOneBy(['slug' => 'linux-base']);
        self::assertInstanceOf(MonitoringTemplate::class, $linuxBase);

        $createInput = new CreateNodeGroupInput();
        $createInput->name = '  Production  ';
        $createInput->description = '  Production nodes  ';
        $createInput->monitoringTemplateIds = [(string) $linuxBase->id()];

        $group = $this->nodeGroupManager->create($createInput);

        self::assertSame('Production', $group->name());
        self::assertSame('Production nodes', $group->description());
        self::assertCount(1, $group->monitoringTemplates());

        $updateInput = new UpdateNodeGroupInput();
        $updateInput->setName('Production Core');
        $updateInput->setDescription('Core production nodes');
        $updateInput->setMonitoringTemplateIds([(string) $linuxBase->id()]);

        $updatedGroup = $this->nodeGroupManager->update((string) $group->id(), $updateInput);
        self::assertSame('Production Core', $updatedGroup->name());
        self::assertSame('Core production nodes', $updatedGroup->description());

        $invalidUpdate = new UpdateNodeGroupInput();
        $invalidUpdate->setMonitoringTemplateIds(['01K00000000000000000000000']);

        $this->expectException(ResourceValidationException::class);
        $this->nodeGroupManager->update((string) $group->id(), $invalidUpdate);
    }
}
