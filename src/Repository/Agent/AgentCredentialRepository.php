<?php

declare(strict_types=1);

namespace App\Repository\Agent;

use App\Entity\Agent\Agent;
use App\Entity\Agent\AgentCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgentCredential>
 */
final class AgentCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentCredential::class);
    }

    public function findOneForAuthentication(string $secretHash): ?AgentCredential
    {
        $credential = $this->createQueryBuilder('credential')
            ->addSelect('agent', 'node')
            ->innerJoin('credential.agent', 'agent')
            ->innerJoin('agent.node', 'node')
            ->andWhere('credential.secretHash = :secretHash')
            ->setParameter('secretHash', $secretHash)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        \assert(null === $credential || $credential instanceof AgentCredential);

        return $credential;
    }

    public function findLatestForAgent(Agent $agent): ?AgentCredential
    {
        $credential = $this->createQueryBuilder('credential')
            ->andWhere('credential.agent = :agent')
            ->setParameter('agent', $agent)
            ->orderBy('credential.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        \assert(null === $credential || $credential instanceof AgentCredential);

        return $credential;
    }
}
