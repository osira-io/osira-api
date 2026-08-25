<?php

declare(strict_types=1);

namespace App\Repository\Incident;

use App\Entity\Incident\Incident;
use App\Entity\Incident\IncidentActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;

/** @extends ServiceEntityRepository<IncidentActivity> */
final class IncidentActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IncidentActivity::class);
    }

    public function countForIncident(Incident $incident): int
    {
        return (int) $this->createQueryBuilder('activity')
            ->select('COUNT(activity.id)')
            ->andWhere('IDENTITY(activity.incident) = :incidentId')
            ->setParameter('incidentId', $incident->id(), UlidType::NAME)
            ->getQuery()->getSingleScalarResult();
    }

    /** @return list<IncidentActivity> */
    public function findTimelineSlice(Incident $incident, int $offset, int $limit): array
    {
        $results = $this->createQueryBuilder('activity')
            ->andWhere('IDENTITY(activity.incident) = :incidentId')->setParameter('incidentId', $incident->id(), UlidType::NAME)
            ->orderBy('activity.createdAt', 'ASC')->addOrderBy('activity.id', 'ASC')
            ->setFirstResult($offset)->setMaxResults($limit)->getQuery()->toIterable();

        $items = [];
        foreach ($results as $result) {
            if (!$result instanceof IncidentActivity) {
                throw new \LogicException('Unexpected incident activity query result.');
            }
            $items[] = $result;
        }

        return $items;
    }
}
