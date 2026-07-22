<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Facade;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Cache\PersonalAccessTokenAuthCache;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade\PersonalAccessTokenFacade;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Manager\PersonalAccessTokenManager;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Tests\Data\Entity\PersonalAccessToken;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PersonalAccessTokenFacadeTest extends TestCase
{
    private const int USER_ID = 42;

    private PersonalAccessTokenAuthCache $authCache;
    private PersonalAccessTokenFacade $facade;
    private ?object $persistedEntity = null;

    public static function setUpBeforeClass(): void
    {
        AnzuApp::init(
            appNamespace: 'anzusystems',
            appSystem: 'authbundle',
            appVersion: 'test',
            appReadOnlyMode: false,
            projectDir: sys_get_temp_dir(),
            appEnv: 'test',
        );
    }

    protected function setUp(): void
    {
        $innerValidator = $this->createMock(ValidatorInterface::class);
        $innerValidator->method('validate')
            ->willReturn(new ConstraintViolationList());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')
            ->willReturnCallback(function (object $entity): void {
                $this->persistedEntity = $entity;
            });

        $currentUserProvider = $this->createMock(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($this->createConfiguredMock(AnzuUser::class, ['getId' => self::USER_ID]));

        $manager = new PersonalAccessTokenManager();
        $manager->setEntityManager($entityManager);
        $manager->setCurrentAnzuUserProvider($currentUserProvider);

        $this->authCache = new PersonalAccessTokenAuthCache(new ArrayAdapter());
        $this->facade = new PersonalAccessTokenFacade(
            new Validator($innerValidator),
            $manager,
            $this->authCache,
            PersonalAccessToken::class,
        );
        $this->persistedEntity = null;
    }

    public function testCreateProducesPrefixedTokenMatchingStoredHash(): void
    {
        $user = $this->createConfiguredMock(AnzuUser::class, ['getId' => self::USER_ID]);

        $result = $this->facade->create($user, 'test-token');

        self::assertStringStartsWith(AbstractPersonalAccessToken::TOKEN_PREFIX, $result->token);
        self::assertSame(
            AbstractPersonalAccessToken::TOKEN_PREFIX . str_repeat('0', PersonalAccessTokenFacade::TOKEN_BYTES_LENGTH * 2),
            preg_replace('~[0-9a-f]~', '0', $result->token),
        );
        self::assertSame(AbstractPersonalAccessToken::hashToken($result->token), $result->personalAccessToken->getTokenHash());
        self::assertSame('test-token', $result->personalAccessToken->getName());
        self::assertSame($user, $result->personalAccessToken->getUser());
        self::assertSame($result->personalAccessToken, $this->persistedEntity);
    }

    public function testCreateRespectsExplicitExpiresAt(): void
    {
        $expiresAt = new DateTimeImmutable('+3 days');

        $result = $this->facade->create($this->createConfiguredMock(AnzuUser::class, []), 'test-token', $expiresAt);

        self::assertSame($expiresAt, $result->personalAccessToken->getExpiresAt());
    }

    public function testRevokeSetsRevokedAtAndInvalidatesCache(): void
    {
        $result = $this->facade->create($this->createConfiguredMock(AnzuUser::class, []), 'test-token');
        $tokenHash = $result->personalAccessToken->getTokenHash();
        $version = $this->authCache->getInvalidationVersion($tokenHash);
        $this->authCache->storeUserId($tokenHash, $version, self::USER_ID, AnzuApp::date('+1 hour'));

        $revoked = $this->facade->revoke($result->personalAccessToken);

        self::assertTrue($revoked->isRevoked());
        self::assertNull($this->authCache->getUserId($tokenHash, $this->authCache->getInvalidationVersion($tokenHash)));
    }

    public function testRevokeIsIdempotent(): void
    {
        $result = $this->facade->create($this->createConfiguredMock(AnzuUser::class, []), 'test-token');
        $revoked = $this->facade->revoke($result->personalAccessToken);
        $revokedAt = $revoked->getRevokedAt();

        self::assertSame($revokedAt, $this->facade->revoke($revoked)->getRevokedAt());
    }
}
