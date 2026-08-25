<?php

declare(strict_types=1);

namespace App\State\Processor\Monitoring;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Monitoring\CreateItemDefinitionInput;
use App\Dto\Monitoring\ItemDefinitionOutput;
use App\Security\Rbac\PermissionCode;
use App\Service\Monitoring\ItemDefinitionManager;
use App\Service\Monitoring\ItemDefinitionOutputFactory;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** @implements ProcessorInterface<CreateItemDefinitionInput, ItemDefinitionOutput> */
final readonly class CreateItemDefinitionProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemDefinitionManager $manager,
        private ItemDefinitionOutputFactory $outputFactory,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ItemDefinitionOutput
    {
        if (!$this->authorizationChecker->isGranted(PermissionCode::ITEM_DEFINITIONS_MANAGE_COMMANDS)) {
            throw new AccessDeniedException('Managing collection commands requires the item_definitions.manage_commands permission.');
        }

        return $this->outputFactory->create($this->manager->create($data));
    }
}
