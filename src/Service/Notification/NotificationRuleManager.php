<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Dto\Notification\CreateNotificationRuleInput;
use App\Dto\Notification\UpdateNotificationRuleInput;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Entity\Notification\NotificationChannel;
use App\Entity\Notification\NotificationRule;
use App\Factory\Notification\NotificationRuleFactory;
use App\Repository\Node\NodeRepository;
use App\Repository\NodeGroup\NodeGroupRepository;
use App\Repository\Notification\NotificationChannelRepository;
use App\Repository\Notification\NotificationRuleRepository;
use App\Service\Shared\Exception\ResourceNotFoundException;
use App\Service\Shared\Exception\ResourceValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class NotificationRuleManager
{
    public function __construct(
        private NotificationRuleRepository $repository,
        private NotificationChannelRepository $channels,
        private NodeRepository $nodes,
        private NodeGroupRepository $nodeGroups,
        private NotificationRuleFactory $factory,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function create(CreateNotificationRuleInput $input): NotificationRule
    {
        $now = $this->clock->now();
        $rule = $this->factory->create($input->name, $input->isEnabled, $this->severities($input->severities), $now);
        $rule->replaceChannels($this->resolve($input->channelIds, $this->channels, NotificationChannel::class, 'Notification channel'), $now);
        $rule->replaceNodes($this->resolve($input->nodeIds, $this->nodes, Node::class, 'Node'), $now);
        $rule->replaceNodeGroups($this->resolve($input->nodeGroupIds, $this->nodeGroups, NodeGroup::class, 'Node group'), $now);
        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        return $rule;
    }

    public function update(string $id, UpdateNotificationRuleInput $input): NotificationRule
    {
        $rule = $this->find($id);
        $now = $this->clock->now();
        $rule->update(null === $input->name ? $rule->name() : $this->factory->normalizeName($input->name), $input->isEnabled ?? $rule->isEnabled(), null === $input->severities ? $rule->severities() : $this->severities($input->severities), $now);
        if (null !== $input->channelIds) {
            $rule->replaceChannels($this->resolve($input->channelIds, $this->channels, NotificationChannel::class, 'Notification channel'), $now);
        }
        if (null !== $input->nodeIds) {
            $rule->replaceNodes($this->resolve($input->nodeIds, $this->nodes, Node::class, 'Node'), $now);
        }
        if (null !== $input->nodeGroupIds) {
            $rule->replaceNodeGroups($this->resolve($input->nodeGroupIds, $this->nodeGroups, NodeGroup::class, 'Node group'), $now);
        }
        $this->entityManager->flush();

        return $rule;
    }

    public function delete(string $id): void
    {
        $this->entityManager->remove($this->find($id));
        $this->entityManager->flush();
    }

    public function find(string $id): NotificationRule
    {
        $rule = Ulid::isValid($id) ? $this->repository->find(new Ulid($id)) : null;
        if (!$rule instanceof NotificationRule) {
            throw new ResourceNotFoundException('Notification rule not found.');
        }

        return $rule;
    }

    /** @param list<string> $values
     * @return list<AlertSeverity>
     */
    private function severities(array $values): array
    {
        return array_map(AlertSeverity::from(...), $values);
    }

    /** @template T of object
     * @param list<string> $ids
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function resolve(array $ids, NotificationChannelRepository|NodeRepository|NodeGroupRepository $repository, string $class, string $label): array
    {
        $entities = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $entity = Ulid::isValid($id) ? $repository->find(new Ulid($id)) : null;
            if (!$entity instanceof $class) {
                throw new ResourceValidationException(\sprintf('%s "%s" does not exist.', $label, $id));
            }
            $entities[] = $entity;
        }

        return $entities;
    }
}
