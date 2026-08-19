<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Dto\Monitoring\ItemDefinitionOutput;
use App\Dto\Monitoring\ItemDefinitionSummary;
use App\Entity\Monitoring\ItemDefinition;

final readonly class ItemDefinitionOutputFactory
{
    public function create(ItemDefinition $itemDefinition): ItemDefinitionOutput
    {
        return new ItemDefinitionOutput(
            (string) $itemDefinition->id(),
            $itemDefinition->key(),
            $itemDefinition->name(),
            $itemDefinition->description(),
            $itemDefinition->category(),
            $itemDefinition->unit(),
            $itemDefinition->valueType()->value,
            $itemDefinition->intervalSeconds(),
            $itemDefinition->timeoutSeconds(),
            $itemDefinition->isSystem(),
            $itemDefinition->isEnabled(),
            $itemDefinition->createdAt(),
            $itemDefinition->updatedAt(),
        );
    }

    public function createSummary(ItemDefinition $itemDefinition): ItemDefinitionSummary
    {
        return new ItemDefinitionSummary(
            (string) $itemDefinition->id(),
            $itemDefinition->key(),
            $itemDefinition->name(),
            $itemDefinition->valueType()->value,
            $itemDefinition->isEnabled(),
        );
    }
}
