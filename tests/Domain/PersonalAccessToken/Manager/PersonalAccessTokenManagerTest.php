<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Manager;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Manager\PersonalAccessTokenManager;
use AnzuSystems\AuthBundle\Tests\Data\Entity\PersonalAccessToken;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PersonalAccessTokenManagerTest extends TestCase
{
    public function testDeleteRemovesAndFlushes(): void
    {
        $personalAccessToken = new PersonalAccessToken();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('remove')
            ->with($personalAccessToken);
        $entityManager->expects($this->once())
            ->method('flush');

        $this->createManager($entityManager)
            ->delete($personalAccessToken);
    }

    public function testDeleteWithoutFlushOnlyRemoves(): void
    {
        $personalAccessToken = new PersonalAccessToken();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('remove')
            ->with($personalAccessToken);
        $entityManager->expects($this->never())
            ->method('flush');

        $this->createManager($entityManager)
            ->delete($personalAccessToken, false);
    }

    private function createManager(EntityManagerInterface $entityManager): PersonalAccessTokenManager
    {
        $manager = new PersonalAccessTokenManager();
        $manager->setEntityManager($entityManager);

        return $manager;
    }
}
