<?php

declare(strict_types=1);

namespace App\Repository\Incident;

use App\Entity\Incident\Incident;
use App\Entity\Node\Node;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<Incident> */
final class IncidentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Incident::class);
    }

    public function findActiveByIdentity(string $identity): ?Incident
    {
        $incident = $this->findOneBy(['activeIdentity' => $identity]);

        return $incident instanceof Incident ? $incident : null;
    }

    /** @return list<Incident> */
    public function findActiveForRuleAndNode(string $ruleId, string $nodeId): array
    {
        $results = $this->createQueryBuilder('incident')
            ->andWhere('IDENTITY(incident.alertRule) = :ruleId')
            ->andWhere('IDENTITY(incident.node) = :nodeId')
            ->andWhere('incident.activeIdentity IS NOT NULL')
            ->setParameter('ruleId', new Ulid($ruleId), UlidType::NAME)
            ->setParameter('nodeId', new Ulid($nodeId), UlidType::NAME)
            ->getQuery()->toIterable();

        $incidents = [];
        foreach ($results as $result) {
            if (!$result instanceof Incident) {
                throw new \LogicException('Unexpected active incident query result.');
            }
            $incidents[] = $result;
        }

        return $incidents;
    }

    /** @param array{status?: string, severity?: string, node?: string, alertRule?: string, date?: \DateTimeImmutable} $filters */
    public function createFilteredQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('incident')
            ->addSelect('node', 'alertRule', 'acknowledgedBy')
            ->join('incident.node', 'node')
            ->join('incident.alertRule', 'alertRule')
            ->leftJoin('incident.acknowledgedBy', 'acknowledgedBy')
            ->orderBy('incident.firstTriggeredAt', 'DESC')
            ->addOrderBy('incident.id', 'ASC');
        foreach (['status', 'severity'] as $field) {
            if (isset($filters[$field])) {
                $qb->andWhere(\sprintf('incident.%s = :%s', $field, $field))->setParameter($field, $filters[$field]);
            }
        }
        foreach (['node', 'alertRule'] as $field) {
            if (isset($filters[$field])) {
                $qb->andWhere(\sprintf('IDENTITY(incident.%s) = :%s', $field, $field))->setParameter($field, new Ulid($filters[$field]), UlidType::NAME);
            }
        }
        if (isset($filters['date'])) {
            $qb->andWhere('incident.firstTriggeredAt >= :date')->setParameter('date', $filters['date']);
        }

        return $qb;
    }

    public function findWithRelations(Ulid $id): ?Incident
    {
        $incident = $this->createQueryBuilder('incident')
            ->addSelect('node', 'alertRule', 'acknowledgedBy')
            ->join('incident.node', 'node')
            ->join('incident.alertRule', 'alertRule')
            ->leftJoin('incident.acknowledgedBy', 'acknowledgedBy')
            ->andWhere('incident.id = :id')
            ->setParameter('id', $id, UlidType::NAME)
            ->getQuery()->getOneOrNullResult();

        return $incident instanceof Incident ? $incident : null;
    }

    public function findForInteraction(Ulid $id, ?LockMode $lockMode = null): ?Incident
    {
        if (null !== $lockMode) {
            $query = $this->createQueryBuilder('incident')
                ->select('incident.id')
                ->andWhere('incident.id = :id')
                ->setParameter('id', $id, UlidType::NAME)
                ->getQuery();
            $query->setLockMode($lockMode);
            if (null === $query->getOneOrNullResult()) {
                return null;
            }

            return $this->findWithRelations($id);
        }

        return $this->findWithRelations($id);
    }

    /** Eagerly loads the AlertRule so callers can inspect its impact classification without triggering N+1 queries.
     * @return list<Incident>
     */
    public function findIntersectingForNode(Node $node, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $results = $this->createQueryBuilder('incident')
            ->addSelect('alertRule')
            ->join('incident.alertRule', 'alertRule')
            ->andWhere('IDENTITY(incident.node) = :nodeId')
            ->andWhere('incident.firstTriggeredAt < :to')
            ->andWhere('incident.resolvedAt IS NULL OR incident.resolvedAt > :from')
            ->setParameter('nodeId', $node->id(), UlidType::NAME)
            ->setParameter('from', $from, Types::DATETIME_IMMUTABLE)
            ->setParameter('to', $to, Types::DATETIME_IMMUTABLE)
            ->orderBy('incident.firstTriggeredAt', 'ASC')->getQuery()->getResult();

        $incidents = [];
        foreach (\is_array($results) ? $results : [] as $result) {
            if ($result instanceof Incident) {
                $incidents[] = $result;
            }
        }

        return $incidents;
    }
}
