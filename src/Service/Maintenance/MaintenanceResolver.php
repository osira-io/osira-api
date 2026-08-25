<?php

declare(strict_types=1);

namespace App\Service\Maintenance;

use App\Entity\Maintenance\MaintenanceWindow;
use App\Entity\Node\Node;
use App\Repository\Maintenance\MaintenanceWindowRepository;
use Psr\Clock\ClockInterface;

final readonly class MaintenanceResolver
{
    public function __construct(
        private ClockInterface $clock,
        private ?MaintenanceWindowRepository $repository = null,
    ) {
    }

    /** @param iterable<MaintenanceWindow>|null $windows */
    public function isInMaintenance(Node $node, ?iterable $windows = null): bool
    {
        return [] !== $this->activeWindowsForNode($node, $windows);
    }

    /**
     * @param iterable<MaintenanceWindow>|null $windows
     *
     * @return list<MaintenanceWindow>
     */
    public function activeWindowsForNode(Node $node, ?iterable $windows = null): array
    {
        $now = $this->clock->now();
        if (null === $windows) {
            if (!$this->repository instanceof MaintenanceWindowRepository) {
                throw new \LogicException('A maintenance window repository is required for database-backed resolution.');
            }

            return $this->repository->findActiveForNode($node, $now);
        }

        $activeById = [];
        foreach ($windows as $window) {
            if ($window->isActiveAt($now) && $window->targetsNode($node)) {
                $activeById[(string) $window->id()] = $window;
            }
        }
        $active = array_values($activeById);
        usort($active, static fn (MaintenanceWindow $left, MaintenanceWindow $right): int => [
            $left->name(),
            $left->startsAt(),
            (string) $left->id(),
        ] <=> [
            $right->name(),
            $right->startsAt(),
            (string) $right->id(),
        ]);

        return $active;
    }
}
