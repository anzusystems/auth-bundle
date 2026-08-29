<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Facade;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Cache\PersonalAccessTokenAuthCache;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade\PersonalAccessTokenFacade;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Manager\PersonalAccessTokenManager;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\CachedPersonalAccessToken;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Tests\Data\Entity\PersonalAccessToken;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\Entity\AnzuUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PersonalAccessTokenFacadeTest extends TestCase
{
    private const int USER_ID = 42;
    private const int PERSONAL_ACCESS_TOKEN_ID = 7;
    private const int RATE_LIMIT = 600;

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
        $innerValidator = $this->createStub(ValidatorInterface::class);
        $innerValidator->method('validate')
            ->willReturn(new ConstraintViolationList());

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')
            ->willReturnCallback(function (object $entity): void {
                $this->persistedEntity = $entity;
            });

        $currentUserProvider = $this->createStub(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($this->createConfiguredStub(AnzuUser::class, ['getId' => self::USER_ID]));

        $manager = new PersonalAccessTokenManager();
        $manager->setEntityManager($entityManager);
        $manager->setCurrentAnzuUserProvider($currentUserProvider);

        $repositoryEntityManager = $this->createStub(EntityManagerInterface::class);
        $repositoryEntityManager->method('getClassMetadata')
            ->willReturn(new ClassMetadata(PersonalAccessToken::class));
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->willReturn($repositoryEntityManager);

        $this->authCache = new PersonalAccessTokenAuthCache(new ArrayAdapter());
        $this->facade = new PersonalAccessTokenFacade(
            new Validator($innerValidator),
            $manager,
            new PersonalAccessTokenRepository($registry, PersonalAccessToken::class),
            $this->authCache,
            PersonalAccessToken::class,
        );
        $this->persistedEntity = null;
    }

    public function testCreateProducesPrefixedTokenMatchingStoredHash(): void
    {
        $user = $this->createConfiguredStub(AnzuUser::class, ['getId' => self::USER_ID]);

        $result = $this->facade->create($user, 'test-token');

        self::assertStringStartsWith(AbstractPersonalAccessToken::TOKEN_PREFIX, $result->token);
        self::assertMatchesRegularExpression(
            sprintf('~^[0-9a-f]{%d}$~', PersonalAccessTokenFacade::TOKEN_BYTES_LENGTH * 2),
            substr($result->token, strlen(AbstractPersonalAccessToken::TOKEN_PREFIX)),
        );
        self::assertSame(AbstractPersonalAccessToken::hashToken($result->token), $result->personalAccessToken->getTokenHash());
        self::assertSame('test-token', $result->personalAccessToken->getName());
        self::assertSame($user, $result->personalAccessToken->getUser());
        self::assertSame($result->personalAccessToken, $this->persistedEntity);
    }

    public function testCreateRespectsExplicitExpiresAt(): void
    {
        $expiresAt = new DateTimeImmutable('+3 days');

        $result = $this->facade->create($this->createConfiguredStub(AnzuUser::class, []), 'test-token', $expiresAt);

        self::assertSame($expiresAt, $result->personalAccessToken->getExpiresAt());
        self::assertNull($result->personalAccessToken->getRateLimit());
    }

    public function testCreateNeverExpiringTokenWithRateLimit(): void
    {
        $result = $this->facade->create(
            $this->createConfiguredStub(AnzuUser::class, []),
            'test-token',
            rateLimit: self::RATE_LIMIT,
            neverExpires: true,
        );

        self::assertNull($result->personalAccessToken->getExpiresAt());
        self::assertSame(self::RATE_LIMIT, $result->personalAccessToken->getRateLimit());
    }

    public function testCreateRejectsExpiresAtCombinedWithNeverExpires(): void
    {
        $this->expectException(ValidationException::class);

        $this->facade->create(
            $this->createConfiguredStub(AnzuUser::class, []),
            'test-token',
            new DateTimeImmutable('+3 days'),
            neverExpires: true,
        );
    }

    public function testRevokeSetsRevokedAtAndInvalidatesCache(): void
    {
        $result = $this->facade->create($this->createConfiguredStub(AnzuUser::class, []), 'test-token');
        $tokenHash = $result->personalAccessToken->getTokenHash();
        $version = $this->authCache->getInvalidationVersion($tokenHash);
        $this->authCache->storeToken(
            $tokenHash,
            $version,
            new CachedPersonalAccessToken(self::PERSONAL_ACCESS_TOKEN_ID, self::USER_ID, null),
            AnzuApp::date('+1 hour'),
        );

        $revoked = $this->facade->revoke($result->personalAccessToken);

        self::assertTrue($revoked->isRevoked());
        self::assertNull($this->authCache->getToken($tokenHash, $this->authCache->getInvalidationVersion($tokenHash)));
    }

    public function testRevokeIsIdempotent(): void
    {
        $result = $this->facade->create($this->createConfiguredStub(AnzuUser::class, []), 'test-token');
        $revoked = $this->facade->revoke($result->personalAccessToken);
        $revokedAt = $revoked->getRevokedAt();

        self::assertSame($revokedAt, $this->facade->revoke($revoked)->getRevokedAt());
    }
}
