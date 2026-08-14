<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EnrollmentToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EnrollmentToken> */
final class EnrollmentTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnrollmentToken::class);
    }

    public function findOneByHashForUpdate(string $tokenHash): ?EnrollmentToken
    {
        $result = $this->createQueryBuilder('token')
            ->andWhere('token.tokenHash = :tokenHash')
            ->setParameter('tokenHash', $tokenHash)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        if (null !== $result && !$result instanceof EnrollmentToken) {
            throw new \LogicException('Unexpected enrollment token query result.');
        }

        return $result;
    }
}
