<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Facade;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade\PersonalAccessTokenFacade;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Manager\PersonalAccessTokenManager;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenCreateDto;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenToolResolver;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Tests\Data\Entity\PersonalAccessToken;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolDefinition;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PersonalAccessTokenFacadeTest extends TestCase
{
    private const int USER_ID = 42;
    private const string WRITE_TOOL = 'example_write';
    private const string READ_TOOL = 'example_read';
    private const string NOT_PERMITTED_READ_TOOL = 'search_app_logs';
    private const string NOT_PERMITTED_WRITE_TOOL = 'example_other_write';
    private const string GRANTED_PERMISSION = 'example_granted';
    private const string DENIED_PERMISSION = 'example_denied';

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

        $security = $this->createStub(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(static fn (mixed $attribute): bool => self::GRANTED_PERMISSION === $attribute);

        $toolDefinitions = new McpToolDefinitions([
            self::READ_TOOL => new McpToolDefinition(permission: self::GRANTED_PERMISSION, readOnly: true),
            self::WRITE_TOOL => new McpToolDefinition(permission: self::GRANTED_PERMISSION),
            self::NOT_PERMITTED_READ_TOOL => new McpToolDefinition(permission: self::DENIED_PERMISSION, readOnly: true),
            self::NOT_PERMITTED_WRITE_TOOL => new McpToolDefinition(permission: self::DENIED_PERMISSION),
        ]);
        $this->facade = new PersonalAccessTokenFacade(
            validator: new Validator($innerValidator),
            manager: $manager,
            repository: new PersonalAccessTokenRepository($registry, PersonalAccessToken::class),
            toolResolver: new PersonalAccessTokenToolResolver(
                toolAccessChecker: new McpToolAccessChecker(toolDefinitions: $toolDefinitions, security: $security),
                toolDefinitions: $toolDefinitions,
            ),
            entityClass: PersonalAccessToken::class,
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
        self::assertSame([], $result->personalAccessToken->getWriteTools());
        self::assertSame($result->personalAccessToken, $this->persistedEntity);
    }

    public function testCreateStoresRequestedWriteTools(): void
    {
        $result = $this->facade->create(
            user: $this->createConfiguredStub(AnzuUser::class, []),
            name: 'test-token',
            writeTools: [self::WRITE_TOOL],
        );

        self::assertSame([self::WRITE_TOOL], $result->personalAccessToken->getWriteTools());
    }

    public function testCreateStoresRequestedReadTools(): void
    {
        $result = $this->facade->create(
            user: $this->createConfiguredStub(AnzuUser::class, []),
            name: 'test-token',
            readTools: [self::READ_TOOL],
        );

        self::assertSame([self::READ_TOOL], $result->personalAccessToken->getReadTools());
    }

    public function testCreateWithoutReadToolsDoesNotNarrowTheToken(): void
    {
        $result = $this->facade->create($this->createConfiguredStub(AnzuUser::class, []), 'test-token');

        self::assertNull($result->personalAccessToken->getReadTools());
    }

    public function testCreateGrantedStoresToolsTheUserMayUse(): void
    {
        $result = $this->facade->createGranted(
            $this->createConfiguredStub(AnzuUser::class, []),
            new PersonalAccessTokenCreateDto()
                ->setName('test-token')
                ->setWriteTools([self::WRITE_TOOL])
                ->setReadTools([self::READ_TOOL]),
        );

        self::assertSame([self::WRITE_TOOL], $result->personalAccessToken->getWriteTools());
        self::assertSame([self::READ_TOOL], $result->personalAccessToken->getReadTools());
    }

    /**
     * @param list<string> $writeTools
     * @param list<string>|null $readTools
     * @param list<string> $expectedFields
     * @param list<string> $explicitReadTools
     */
    #[DataProvider('ungrantableToolsDataProvider')]
    public function testCreateGrantedRefusesToolsTheUserMayNotUse(
        array $writeTools,
        ?array $readTools,
        array $expectedFields,
        array $explicitReadTools = [],
    ): void {
        try {
            $this->facade->createGranted(
                $this->createConfiguredStub(AnzuUser::class, []),
                new PersonalAccessTokenCreateDto()
                    ->setName('test-token')
                    ->setWriteTools($writeTools)
                    ->setReadTools($readTools)
                    ->setExplicitReadTools($explicitReadTools),
            );
            self::fail('Expected ' . ValidationException::class);
        } catch (ValidationException $exception) {
            self::assertSame($expectedFields, array_keys($exception->getFormattedErrors()));
        }
        self::assertNull($this->persistedEntity);
    }

    /**
     * @return array<string, array{0: list<string>, 1: list<string>|null, 2: list<string>, 3?: list<string>}>
     */
    public static function ungrantableToolsDataProvider(): array
    {
        return [
            'write tool without permission' => [[self::NOT_PERMITTED_WRITE_TOOL], null, ['writeTools']],
            'read tool without permission' => [[], [self::NOT_PERMITTED_READ_TOOL], ['readTools']],
            'unknown tools in both lists' => [['unknown'], ['unknown'], ['readTools', 'writeTools']],
            'explicit read tool without permission' => [[], null, ['explicitReadTools'], [self::NOT_PERMITTED_READ_TOOL]],
        ];
    }

    public function testCreateRespectsExplicitExpiresAt(): void
    {
        $expiresAt = new DateTimeImmutable('+3 days');

        $result = $this->facade->create($this->createConfiguredStub(AnzuUser::class, []), 'test-token', $expiresAt);

        self::assertSame($expiresAt, $result->personalAccessToken->getExpiresAt());
    }

    public function testCreateNeverExpiringToken(): void
    {
        $result = $this->facade->create(
            $this->createConfiguredStub(AnzuUser::class, []),
            'test-token',
            neverExpires: true,
        );

        self::assertNull($result->personalAccessToken->getExpiresAt());
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

    public function testRevokeIsIdempotent(): void
    {
        $result = $this->facade->create($this->createConfiguredStub(AnzuUser::class, []), 'test-token');
        $revoked = $this->facade->revoke($result->personalAccessToken);
        $revokedAt = $revoked->getRevokedAt();

        self::assertTrue($revoked->isRevoked());
        self::assertSame($revokedAt, $this->facade->revoke($revoked)->getRevokedAt());
    }
}
