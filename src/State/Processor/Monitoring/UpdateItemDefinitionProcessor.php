<?php

declare(strict_types=1);

namespace App\State\Processor\Monitoring;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Monitoring\ItemDefinitionOutput;
use App\Dto\Monitoring\UpdateItemDefinitionInput;
use App\Security\Rbac\PermissionCode;
use App\Service\Monitoring\ItemDefinitionManager;
use App\Service\Monitoring\ItemDefinitionOutputFactory;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** @implements ProcessorInterface<UpdateItemDefinitionInput, ItemDefinitionOutput> */
final readonly class UpdateItemDefinitionProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemDefinitionManager $manager,
        private ItemDefinitionOutputFactory $outputFactory,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ItemDefinitionOutput
    {
        if (($data->isLinuxCommandProvided() || $data->isWindowsCommandProvided())
            && !$this->authorizationChecker->isGranted(PermissionCode::ITEM_DEFINITIONS_MANAGE_COMMANDS)) {
            throw new AccessDeniedException('Managing collection commands requires the item_definitions.manage_commands permission.');
        }
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->manager->update(\is_string($id) ? $id : '', $data));
    }
}
