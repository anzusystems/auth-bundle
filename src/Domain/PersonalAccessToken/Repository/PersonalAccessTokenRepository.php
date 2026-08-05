<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository;

use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\CommonBundle\Repository\AbstractAnzuRepository;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractAnzuRepository<AbstractPersonalAccessToken>
 */
final class PersonalAccessTokenRepository extends AbstractAnzuRepository
{
    /**
     * @param class-string<AbstractPersonalAccessToken> $entityClass
     */
    public function __construct(
        ManagerRegistry $registry,
        private readonly string $entityClass,
    ) {
        parent::__construct($registry);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findOneActiveByTokenHash(string $tokenHash): ?AbstractPersonalAccessToken
    {
        return $this->createQueryBuilder('personalAccessToken')
            ->where('personalAccessToken.tokenHash = :tokenHash')
            ->andWhere('personalAccessToken.revokedAt IS NULL')
            ->andWhere('personalAccessToken.expiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', AnzuApp::date())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Collection<int, AbstractPersonalAccessToken>
     */
    public function findByUser(AnzuUser $user): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('personalAccessToken')
                ->where('personalAccessToken.user = :user')
                ->setParameter('user', $user)
                ->orderBy('personalAccessToken.id', Order::Descending->value)
                ->getQuery()
                ->getResult()
        );
    }

    /**
     * @return Collection<int, AbstractPersonalAccessToken>
     */
    public function findAllActiveExpiringBetween(DateTimeImmutable $from, DateTimeImmutable $until): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('personalAccessToken')
                ->where('personalAccessToken.revokedAt IS NULL')
                ->andWhere('personalAccessToken.expiresAt > :from')
                ->andWhere('personalAccessToken.expiresAt <= :until')
                ->setParameter('from', $from)
                ->setParameter('until', $until)
                ->getQuery()
                ->getResult()
        );
    }

    protected function getEntityClass(): string
    {
        return $this->entityClass;
    }
}
