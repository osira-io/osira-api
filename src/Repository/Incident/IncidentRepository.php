<?php

declare(strict_types=1);

namespace App\Repository\Incident;

use App\Entity\Incident\Incident;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
            ->addSelect('node', 'alertRule')
            ->join('incident.node', 'node')
            ->join('incident.alertRule', 'alertRule')
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
            ->addSelect('node', 'alertRule')
            ->join('incident.node', 'node')
            ->join('incident.alertRule', 'alertRule')
            ->andWhere('incident.id = :id')
            ->setParameter('id', $id, UlidType::NAME)
            ->getQuery()->getOneOrNullResult();

        return $incident instanceof Incident ? $incident : null;
    }
}
