<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\MonitoringTemplate;

final readonly class EffectiveNodeMonitoring
{
    /** @param list<MonitoringTemplate> $templates
     * @param list<ItemDefinition> $items
     */
    public function __construct(
        public array $templates,
        public array $items,
    ) {
    }
}
