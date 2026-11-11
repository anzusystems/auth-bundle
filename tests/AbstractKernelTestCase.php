<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade\PersonalAccessTokenFacade;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Tests\Data\Entity\User;
use AnzuSystems\AuthBundle\Tests\Data\Repository\UserRepository;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

abstract class AbstractKernelTestCase extends AnzuKernelTestCase
{
    protected const string TOKEN_NAME = 'Example token';

    protected function setUp(): void
    {
        parent::setUp();

        $this->getConnection()
            ->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->getConnection()
            ->rollBack();

        parent::tearDown();
    }

    /**
     * @param list<string> $writeTools
     * @param list<string>|null $readTools
     * @param list<string> $explicitReadTools
     */
    protected function createToken(
        int $userId,
        array $writeTools = [],
        ?array $readTools = null,
        array $explicitReadTools = [],
    ): AbstractPersonalAccessToken {
        return self::getContainer()->get(PersonalAccessTokenFacade::class)->create(
            user: $this->getUser($userId),
            name: self::TOKEN_NAME,
            writeTools: $writeTools,
            readTools: $readTools,
            explicitReadTools: $explicitReadTools,
        )->personalAccessToken;
    }

    protected function getUser(int $userId): User
    {
        $user = self::getContainer()->get(UserRepository::class)->find($userId);
        if ($user instanceof User) {
            return $user;
        }

        throw new LogicException(sprintf('User "%d" does not exist, add it to the fixtures.', $userId));
    }

    protected function getStoredToken(int $tokenId): AbstractPersonalAccessToken
    {
        $this->getEntityManager()
            ->clear();
        $personalAccessToken = self::getContainer()->get(PersonalAccessTokenRepository::class)->find($tokenId);
        if ($personalAccessToken instanceof AbstractPersonalAccessToken) {
            return $personalAccessToken;
        }

        throw new LogicException(sprintf('Personal access token "%d" is not stored.', $tokenId));
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function getConnection(): Connection
    {
        return self::getContainer()->get('doctrine.dbal.default_connection');
    }
}
