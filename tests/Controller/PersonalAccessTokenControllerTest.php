<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Controller;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade\PersonalAccessTokenFacade;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenCreateResult;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\AuthBundle\Tests\Data\Fixtures\UserFixtures;
use AnzuSystems\AuthBundle\Tests\Data\Mcp\ExampleTool;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PersonalAccessTokenControllerTest extends AbstractControllerTestCase
{
    private const string URI = '/personal-access-token';
    private const string TOOLS_URI = self::URI . '/tools';
    private const string REVOKE_URI_FORMAT = self::URI . '/%d/revoke';
    private const string UPDATE_TOOLS_URI_FORMAT = self::URI . '/%d/tools';
    private const string TOKEN_API_PREFIX = '/token-api';
    private const string REVOKED_AT = '2020-01-01T00:00:00';
    private const string EXPIRED_AT = '-1 year';
    private const string STATE_REVOKED = 'revoked';
    private const string STATE_EXPIRED = 'expired';
    private const string TOKEN_NAME = 'Example token';
    private const string SERIALIZER_ERROR = 'serializer_error';
    private const int NON_EXISTENT_TOKEN_ID = 999_999;

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $expectedWriteTools
     * @param list<string>|null $expectedReadTools
     * @param list<string> $expectedExplicitReadTools
     */
    #[DataProvider('createDataProvider')]
    public function testCreate(
        int $userId,
        array $payload,
        array $expectedWriteTools,
        ?array $expectedReadTools,
        array $expectedExplicitReadTools,
    ): void {
        $this->loginUser($userId);

        $response = $this->requestJson(Request::METHOD_POST, self::URI, $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertStringStartsWith(AbstractPersonalAccessToken::TOKEN_PREFIX, $response['token']);
        self::assertSame(self::TOKEN_NAME, $response['personalAccessToken']['name']);
        self::assertSame($userId, $response['personalAccessToken']['user']);
        self::assertSame($expectedWriteTools, $response['personalAccessToken']['writeTools']);
        self::assertSame($expectedReadTools, $response['personalAccessToken']['readTools']);
        self::assertSame($expectedExplicitReadTools, $response['personalAccessToken']['explicitReadTools']);
        $personalAccessToken = $this->getRepository()
            ->findOneActiveByTokenHash(AbstractPersonalAccessToken::hashToken($response['token']));
        self::assertSame($response['personalAccessToken']['id'], $personalAccessToken?->getId());
        self::assertSame($expectedExplicitReadTools, $personalAccessToken->getExplicitReadTools());
    }

    /**
     * @return iterable<string, array{int, array<string, mixed>, list<string>, list<string>|null, list<string>}>
     */
    public static function createDataProvider(): iterable
    {
        yield 'only a name, as sent before the tool lists existed' => [
            UserFixtures::TOKEN_MANAGER_ID,
            ['name' => self::TOKEN_NAME],
            [],
            null,
            [],
        ];
        yield 'write and read tools with createWithWriteTools, as sent before the explicit read tools existed' => [
            UserFixtures::TOKEN_MANAGER_ID,
            ['name' => self::TOKEN_NAME, 'writeTools' => [ExampleTool::WRITE], 'readTools' => [ExampleTool::READ]],
            [ExampleTool::WRITE],
            [ExampleTool::READ],
            [],
        ];
        yield 'read and explicit read tools without createWithWriteTools' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            [
                'name' => self::TOKEN_NAME,
                'readTools' => [ExampleTool::READ],
                'explicitReadTools' => [ExampleTool::EXPLICIT_READ],
            ],
            [],
            [ExampleTool::READ],
            [ExampleTool::EXPLICIT_READ],
        ];
        yield 'every read tool together with an explicit read tool' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            ['name' => self::TOKEN_NAME, 'explicitReadTools' => [ExampleTool::EXPLICIT_READ]],
            [],
            null,
            [ExampleTool::EXPLICIT_READ],
        ];
        yield 'no tools without createWithWriteTools' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            ['name' => self::TOKEN_NAME],
            [],
            null,
            [],
        ];
        yield 'empty tool lists without createWithWriteTools' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            ['name' => self::TOKEN_NAME, 'writeTools' => [], 'readTools' => [], 'explicitReadTools' => []],
            [],
            [],
            [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('createForbiddenDataProvider')]
    public function testCreateIsForbidden(int $userId, array $payload): void
    {
        $this->loginUser($userId);

        $this->requestJson(Request::METHOD_POST, self::URI, $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertCount(0, $this->getRepository()->findByUser($this->getUser($userId)));
    }

    /**
     * @return iterable<string, array{int, array<string, mixed>}>
     */
    public static function createForbiddenDataProvider(): iterable
    {
        yield 'a write tool without createWithWriteTools' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            ['name' => self::TOKEN_NAME, 'writeTools' => [ExampleTool::WRITE]],
        ];
        yield 'without the create role' => [
            UserFixtures::WITHOUT_CREATE_ROLE_ID,
            ['name' => self::TOKEN_NAME],
        ];
        yield 'without the create permission' => [
            UserFixtures::WITHOUT_PERMISSIONS_ID,
            ['name' => self::TOKEN_NAME],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, list<string>> $expectedValidationErrors
     */
    #[DataProvider('createValidationDataProvider')]
    public function testCreateValidation(array $payload, array $expectedValidationErrors): void
    {
        $this->loginUser(UserFixtures::TOKEN_MANAGER_ID);

        $response = $this->requestJson(Request::METHOD_POST, self::URI, $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors($response, $expectedValidationErrors);
        self::assertCount(0, $this->getRepository()->findByUser($this->getUser(UserFixtures::TOKEN_MANAGER_ID)));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, list<string>>}>
     */
    public static function createValidationDataProvider(): iterable
    {
        yield 'a read tool the creator has no permission for' => [
            ['name' => self::TOKEN_NAME, 'readTools' => [ExampleTool::READ, ExampleTool::RESTRICTED_READ]],
            ['readTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'a write tool the creator has no permission for' => [
            ['name' => self::TOKEN_NAME, 'writeTools' => [ExampleTool::RESTRICTED_WRITE]],
            ['writeTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'an unknown tool' => [
            ['name' => self::TOKEN_NAME, 'readTools' => ['example_unknown']],
            ['readTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'a write tool among the read tools' => [
            ['name' => self::TOKEN_NAME, 'readTools' => [ExampleTool::WRITE]],
            ['readTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'an explicit read tool among the read tools' => [
            ['name' => self::TOKEN_NAME, 'readTools' => [ExampleTool::EXPLICIT_READ]],
            ['readTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'a read tool among the explicit read tools' => [
            ['name' => self::TOKEN_NAME, 'explicitReadTools' => [ExampleTool::READ]],
            ['explicitReadTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'an explicit read tool listed twice' => [
            ['name' => self::TOKEN_NAME, 'explicitReadTools' => [ExampleTool::EXPLICIT_READ, ExampleTool::EXPLICIT_READ]],
            ['explicitReadTools' => [ValidationException::ERROR_FIELD_UNIQUE]],
        ];
        yield 'a blank name' => [
            ['name' => ''],
            ['name' => [ValidationException::ERROR_FIELD_EMPTY]],
        ];
    }

    public function testGetList(): void
    {
        $olderToken = $this->createToken(UserFixtures::TOKEN_MANAGER_ID);
        $newerToken = $this->createToken(
            userId: UserFixtures::TOKEN_MANAGER_ID,
            writeTools: [ExampleTool::WRITE],
            readTools: [ExampleTool::READ],
            explicitReadTools: [ExampleTool::EXPLICIT_READ],
        );
        $this->createToken(UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID);
        $this->loginUser(UserFixtures::TOKEN_MANAGER_ID);

        $response = $this->requestJson(Request::METHOD_GET, self::URI);

        self::assertResponseIsSuccessful();
        self::assertSame(
            [$newerToken->personalAccessToken->getId(), $olderToken->personalAccessToken->getId()],
            array_column($response, 'id'),
        );
        self::assertSame([ExampleTool::WRITE], $response[0]['writeTools']);
        self::assertSame([ExampleTool::READ], $response[0]['readTools']);
        self::assertSame([ExampleTool::EXPLICIT_READ], $response[0]['explicitReadTools']);
        self::assertSame([], $response[1]['explicitReadTools']);
        self::assertArrayNotHasKey('token', $response[0]);
        self::assertArrayNotHasKey('tokenHash', $response[0]);
    }

    public function testGetListIsForbiddenWithoutPermission(): void
    {
        $this->loginUser(UserFixtures::WITHOUT_PERMISSIONS_ID);

        $this->requestJson(Request::METHOD_GET, self::URI);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * @param list<array{name: string, writeTool: bool, category: string, explicitGrant: bool}> $expectedTools
     */
    #[DataProvider('toolsDataProvider')]
    public function testGetTools(int $userId, array $expectedTools): void
    {
        $this->loginUser($userId);

        $response = $this->requestJson(Request::METHOD_GET, self::TOOLS_URI);

        self::assertResponseIsSuccessful();
        self::assertSame($expectedTools, $response);
    }

    /**
     * @return iterable<string, array{int, list<array{name: string, writeTool: bool, category: string, explicitGrant: bool}>}>
     */
    public static function toolsDataProvider(): iterable
    {
        $readTools = [
            ['name' => ExampleTool::READ, 'writeTool' => false, 'category' => ExampleTool::CATEGORY, 'explicitGrant' => false],
            ['name' => ExampleTool::EXPLICIT_READ, 'writeTool' => false, 'category' => ExampleTool::CATEGORY, 'explicitGrant' => true],
        ];

        yield 'with createWithWriteTools' => [
            UserFixtures::TOKEN_MANAGER_ID,
            [
                ...$readTools,
                ['name' => ExampleTool::WRITE, 'writeTool' => true, 'category' => ExampleTool::CATEGORY, 'explicitGrant' => false],
            ],
        ];
        yield 'without createWithWriteTools' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            $readTools,
        ];
    }

    #[DataProvider('toolsForbiddenDataProvider')]
    public function testGetToolsIsForbidden(int $userId): void
    {
        $this->loginUser($userId);

        $this->requestJson(Request::METHOD_GET, self::TOOLS_URI);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function toolsForbiddenDataProvider(): iterable
    {
        yield 'without the create role' => [UserFixtures::WITHOUT_CREATE_ROLE_ID];
        yield 'without the create permission' => [UserFixtures::WITHOUT_PERMISSIONS_ID];
    }

    public function testRevoke(): void
    {
        $token = $this->createToken(UserFixtures::TOKEN_MANAGER_ID);
        $tokenId = (int) $token->personalAccessToken->getId();
        $this->loginUser(UserFixtures::TOKEN_MANAGER_ID);

        $response = $this->requestJson(Request::METHOD_PATCH, sprintf(self::REVOKE_URI_FORMAT, $tokenId));

        self::assertResponseIsSuccessful();
        self::assertSame($tokenId, $response['id']);
        self::assertNotNull($response['revokedAt']);
        self::assertTrue($this->getRepository()->find($tokenId)?->isRevoked());

        $this->sendBearerToken($token->token);
        $this->requestJson(Request::METHOD_GET, self::TOKEN_API_PREFIX . self::URI);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testRevokeKeepsTheTimeOfARevokedToken(): void
    {
        $personalAccessToken = $this->createToken(UserFixtures::TOKEN_MANAGER_ID)->personalAccessToken;
        $personalAccessToken->setRevokedAt(new DateTimeImmutable(self::REVOKED_AT));
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->loginUser(UserFixtures::TOKEN_MANAGER_ID);

        $response = $this->requestJson(Request::METHOD_PATCH, sprintf(self::REVOKE_URI_FORMAT, (int) $personalAccessToken->getId()));

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith(self::REVOKED_AT, $response['revokedAt']);
    }

    #[DataProvider('revokeForbiddenDataProvider')]
    public function testRevokeIsForbidden(int $ownerId, int $userId): void
    {
        $tokenId = (int) $this->createToken($ownerId)->personalAccessToken->getId();
        $this->loginUser($userId);

        $this->requestJson(Request::METHOD_PATCH, sprintf(self::REVOKE_URI_FORMAT, $tokenId));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertFalse($this->getRepository()->find($tokenId)?->isRevoked());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function revokeForbiddenDataProvider(): iterable
    {
        yield 'a token of another user' => [UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID, UserFixtures::TOKEN_MANAGER_ID];
        yield 'an own token without the revoke permission' => [UserFixtures::WITHOUT_PERMISSIONS_ID, UserFixtures::WITHOUT_PERMISSIONS_ID];
    }

    public function testRevokeOfANonExistentTokenIsNotFound(): void
    {
        $this->loginUser(UserFixtures::TOKEN_MANAGER_ID);

        $this->requestJson(Request::METHOD_PATCH, sprintf(self::REVOKE_URI_FORMAT, self::NON_EXISTENT_TOKEN_ID));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @param list<string> $writeTools
     * @param list<string>|null $readTools
     * @param list<string> $explicitReadTools
     * @param array<string, mixed> $payload
     * @param list<string> $expectedWriteTools
     * @param list<string>|null $expectedReadTools
     * @param list<string> $expectedExplicitReadTools
     */
    #[DataProvider('updateToolsDataProvider')]
    public function testUpdateTools(
        int $userId,
        array $writeTools,
        ?array $readTools,
        array $explicitReadTools,
        array $payload,
        array $expectedWriteTools,
        ?array $expectedReadTools,
        array $expectedExplicitReadTools,
    ): void {
        $personalAccessToken = $this->createToken(
            userId: $userId,
            writeTools: $writeTools,
            readTools: $readTools,
            explicitReadTools: $explicitReadTools,
        )->personalAccessToken;
        $tokenId = (int) $personalAccessToken->getId();
        $expiresAt = $personalAccessToken->getExpiresAt();
        $modifiedAt = new DateTimeImmutable(self::REVOKED_AT);
        $personalAccessToken->setModifiedAt($modifiedAt);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->loginUser($userId);

        $response = $this->requestJson(Request::METHOD_PUT, sprintf(self::UPDATE_TOOLS_URI_FORMAT, $tokenId), $payload);

        self::assertResponseIsSuccessful();
        self::assertSame($tokenId, $response['id']);
        self::assertSame(self::TOKEN_NAME, $response['name']);
        self::assertSame($expectedWriteTools, $response['writeTools']);
        self::assertSame($expectedReadTools, $response['readTools']);
        self::assertSame($expectedExplicitReadTools, $response['explicitReadTools']);
        self::assertArrayNotHasKey('token', $response);
        self::assertArrayNotHasKey('tokenHash', $response);
        $storedToken = $this->getStoredToken($tokenId);
        self::assertSame($expectedWriteTools, $storedToken->getWriteTools());
        self::assertSame($expectedReadTools, $storedToken->getReadTools());
        self::assertSame($expectedExplicitReadTools, $storedToken->getExplicitReadTools());
        self::assertSame(self::TOKEN_NAME, $storedToken->getName());
        self::assertSame($expiresAt?->getTimestamp(), $storedToken->getExpiresAt()?->getTimestamp());
        self::assertGreaterThan($modifiedAt, $storedToken->getModifiedAt());
    }

    /**
     * @return iterable<string, array{int, list<string>, list<string>|null, list<string>, array<string, mixed>, list<string>, list<string>|null, list<string>}>
     */
    public static function updateToolsDataProvider(): iterable
    {
        yield 'lists replaced by other lists' => [
            UserFixtures::TOKEN_MANAGER_ID,
            [ExampleTool::WRITE],
            [ExampleTool::READ],
            [],
            ['readTools' => [], 'explicitReadTools' => [ExampleTool::EXPLICIT_READ], 'writeTools' => [ExampleTool::WRITE]],
            [ExampleTool::WRITE],
            [],
            [ExampleTool::EXPLICIT_READ],
        ];
        yield 'every read tool narrowed to a list' => [
            UserFixtures::TOKEN_MANAGER_ID,
            [],
            null,
            [],
            ['readTools' => [ExampleTool::READ], 'writeTools' => []],
            [],
            [ExampleTool::READ],
            [],
        ];
        yield 'read tools left out mean every read tool' => [
            UserFixtures::TOKEN_MANAGER_ID,
            [],
            [ExampleTool::READ],
            [],
            ['writeTools' => []],
            [],
            null,
            [],
        ];
        yield 'explicit read tools left out mean none, as sent before they existed' => [
            UserFixtures::TOKEN_MANAGER_ID,
            [],
            [ExampleTool::READ],
            [ExampleTool::EXPLICIT_READ],
            ['readTools' => [ExampleTool::READ], 'writeTools' => []],
            [],
            [ExampleTool::READ],
            [],
        ];
        yield 'every read tool together with an explicit read tool' => [
            UserFixtures::TOKEN_MANAGER_ID,
            [],
            [ExampleTool::READ],
            [],
            ['readTools' => null, 'explicitReadTools' => [ExampleTool::EXPLICIT_READ], 'writeTools' => []],
            [],
            null,
            [ExampleTool::EXPLICIT_READ],
        ];
        yield 'no tool at all' => [
            UserFixtures::TOKEN_MANAGER_ID,
            [ExampleTool::WRITE],
            [ExampleTool::READ],
            [ExampleTool::EXPLICIT_READ],
            ['readTools' => [], 'explicitReadTools' => [], 'writeTools' => []],
            [],
            [],
            [],
        ];
        yield 'read and explicit read tools without createWithWriteTools' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            [],
            null,
            [],
            ['readTools' => [ExampleTool::READ], 'explicitReadTools' => [ExampleTool::EXPLICIT_READ], 'writeTools' => []],
            [],
            [ExampleTool::READ],
            [ExampleTool::EXPLICIT_READ],
        ];
        yield 'a write tool removed without createWithWriteTools' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            [ExampleTool::WRITE],
            [ExampleTool::READ],
            [],
            ['readTools' => [ExampleTool::READ], 'writeTools' => []],
            [],
            [ExampleTool::READ],
            [],
        ];
    }

    public function testUpdateToolsAppliesFromTheNextRequest(): void
    {
        $token = $this->createToken(
            userId: UserFixtures::TOKEN_MANAGER_ID,
            writeTools: [ExampleTool::WRITE],
            readTools: [ExampleTool::READ],
            explicitReadTools: [ExampleTool::EXPLICIT_READ],
        );
        $tokenId = (int) $token->personalAccessToken->getId();
        $this->loginUser(UserFixtures::TOKEN_MANAGER_ID);

        $this->requestJson(
            Request::METHOD_PUT,
            sprintf(self::UPDATE_TOOLS_URI_FORMAT, $tokenId),
            ['readTools' => [], 'explicitReadTools' => [], 'writeTools' => []],
        );

        self::assertResponseIsSuccessful();
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $activeToken = $this->getRepository()
            ->findOneActiveByTokenHash(AbstractPersonalAccessToken::hashToken($token->token));
        self::assertSame($tokenId, $activeToken?->getId());
        self::assertSame([], $activeToken->getReadTools());
        self::assertSame([], $activeToken->getExplicitReadTools());
        self::assertSame([], $activeToken->getWriteTools());

        $this->sendBearerToken($token->token);
        $this->requestJson(Request::METHOD_GET, self::TOKEN_API_PREFIX . self::URI);

        // The token still authenticates: 401 would mean its value stopped working, 403 is the voter refusing token management.
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('updateToolsForbiddenDataProvider')]
    public function testUpdateToolsIsForbidden(int $ownerId, int $userId, array $payload): void
    {
        $tokenId = (int) $this->createToken($ownerId, [ExampleTool::WRITE], [ExampleTool::READ])->personalAccessToken->getId();
        $this->loginUser($userId);

        $this->requestJson(Request::METHOD_PUT, sprintf(self::UPDATE_TOOLS_URI_FORMAT, $tokenId), $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertStoredTools($tokenId, [ExampleTool::WRITE], [ExampleTool::READ]);
    }

    /**
     * @return iterable<string, array{int, int, array<string, mixed>}>
     */
    public static function updateToolsForbiddenDataProvider(): iterable
    {
        $readOnlyPayload = ['readTools' => [], 'explicitReadTools' => [ExampleTool::EXPLICIT_READ], 'writeTools' => []];

        yield 'a token of another user' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            UserFixtures::TOKEN_MANAGER_ID,
            $readOnlyPayload,
        ];
        yield 'a write tool without createWithWriteTools' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            ['readTools' => [], 'writeTools' => [ExampleTool::WRITE]],
        ];
        yield 'an own token without the create role' => [
            UserFixtures::WITHOUT_CREATE_ROLE_ID,
            UserFixtures::WITHOUT_CREATE_ROLE_ID,
            $readOnlyPayload,
        ];
        yield 'an own token without the create permission' => [
            UserFixtures::WITHOUT_PERMISSIONS_ID,
            UserFixtures::WITHOUT_PERMISSIONS_ID,
            $readOnlyPayload,
        ];
    }

    /**
     * @param list<string> $states
     * @param array<string, mixed> $payload
     * @param array<string, list<string>> $expectedValidationErrors
     */
    #[DataProvider('updateToolsValidationDataProvider')]
    public function testUpdateToolsValidation(array $states, array $payload, array $expectedValidationErrors): void
    {
        $personalAccessToken = $this->createToken(UserFixtures::TOKEN_MANAGER_ID, [ExampleTool::WRITE], [ExampleTool::READ])->personalAccessToken;
        if (in_array(needle: self::STATE_REVOKED, haystack: $states, strict: true)) {
            $personalAccessToken->setRevokedAt(new DateTimeImmutable(self::REVOKED_AT));
        }
        if (in_array(needle: self::STATE_EXPIRED, haystack: $states, strict: true)) {
            $personalAccessToken->setExpiresAt(new DateTimeImmutable(self::EXPIRED_AT));
        }
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $tokenId = (int) $personalAccessToken->getId();
        $this->loginUser(UserFixtures::TOKEN_MANAGER_ID);

        $response = $this->requestJson(Request::METHOD_PUT, sprintf(self::UPDATE_TOOLS_URI_FORMAT, $tokenId), $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors($response, $expectedValidationErrors);
        $this->assertStoredTools($tokenId, [ExampleTool::WRITE], [ExampleTool::READ]);
    }

    /**
     * @return iterable<string, array{list<string>, array<string, mixed>, array<string, list<string>>}>
     */
    public static function updateToolsValidationDataProvider(): iterable
    {
        $validPayload = ['readTools' => [], 'explicitReadTools' => [ExampleTool::EXPLICIT_READ], 'writeTools' => []];

        yield 'a revoked token' => [
            [self::STATE_REVOKED],
            $validPayload,
            ['revokedAt' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'an expired token' => [
            [self::STATE_EXPIRED],
            $validPayload,
            ['expiresAt' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'a revoked and expired token' => [
            [self::STATE_REVOKED, self::STATE_EXPIRED],
            $validPayload,
            [
                'revokedAt' => [ValidationException::ERROR_FIELD_INVALID],
                'expiresAt' => [ValidationException::ERROR_FIELD_INVALID],
            ],
        ];
        yield 'write tools left out' => [
            [],
            ['readTools' => [], 'explicitReadTools' => [ExampleTool::EXPLICIT_READ]],
            ['writeTools' => [ValidationException::ERROR_FIELD_EMPTY]],
        ];
        yield 'write tools sent as null' => [
            [],
            ['readTools' => [], 'explicitReadTools' => [ExampleTool::EXPLICIT_READ], 'writeTools' => null],
            ['writeTools' => [ValidationException::ERROR_FIELD_EMPTY]],
        ];
        yield 'a read tool the owner has no permission for' => [
            [],
            ['readTools' => [ExampleTool::READ, ExampleTool::RESTRICTED_READ], 'writeTools' => []],
            ['readTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'a write tool the owner has no permission for' => [
            [],
            ['readTools' => [], 'writeTools' => [ExampleTool::RESTRICTED_WRITE]],
            ['writeTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'an unknown tool' => [
            [],
            ['readTools' => ['example_unknown'], 'writeTools' => []],
            ['readTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'a write tool among the read tools' => [
            [],
            ['readTools' => [ExampleTool::WRITE], 'writeTools' => []],
            ['readTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'a tool listed twice' => [
            [],
            ['readTools' => [ExampleTool::READ, ExampleTool::READ], 'writeTools' => []],
            ['readTools' => [ValidationException::ERROR_FIELD_UNIQUE]],
        ];
        yield 'an explicit read tool among the read tools' => [
            [],
            ['readTools' => [ExampleTool::EXPLICIT_READ], 'writeTools' => []],
            ['readTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'a read tool among the explicit read tools' => [
            [],
            ['readTools' => [], 'explicitReadTools' => [ExampleTool::READ], 'writeTools' => []],
            ['explicitReadTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'an unknown explicit read tool' => [
            [],
            ['readTools' => [], 'explicitReadTools' => ['example_unknown'], 'writeTools' => []],
            ['explicitReadTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'an explicit read tool listed twice' => [
            [],
            [
                'readTools' => [],
                'explicitReadTools' => [ExampleTool::EXPLICIT_READ, ExampleTool::EXPLICIT_READ],
                'writeTools' => [],
            ],
            ['explicitReadTools' => [ValidationException::ERROR_FIELD_UNIQUE]],
        ];
    }

    public function testUpdateToolsRefusesExplicitReadToolsSentAsNull(): void
    {
        $tokenId = (int) $this->createToken(
            userId: UserFixtures::TOKEN_MANAGER_ID,
            writeTools: [ExampleTool::WRITE],
            readTools: [ExampleTool::READ],
            explicitReadTools: [ExampleTool::EXPLICIT_READ],
        )->personalAccessToken->getId();
        $this->loginUser(UserFixtures::TOKEN_MANAGER_ID);

        $response = $this->requestJson(
            Request::METHOD_PUT,
            sprintf(self::UPDATE_TOOLS_URI_FORMAT, $tokenId),
            ['readTools' => null, 'explicitReadTools' => null, 'writeTools' => []],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(self::SERIALIZER_ERROR, $response['error']);
        $this->assertStoredTools($tokenId, [ExampleTool::WRITE], [ExampleTool::READ]);
        self::assertSame([ExampleTool::EXPLICIT_READ], $this->getStoredToken($tokenId)->getExplicitReadTools());
    }

    /**
     * @param list<string> $readTools
     * @param list<string>|null $expectedReadTools
     */
    #[DataProvider('superAdminUpdateToolsDataProvider')]
    public function testSuperAdminUpdatesToolsOfAnotherUser(array $readTools, int $expectedStatusCode, ?array $expectedReadTools): void
    {
        $tokenId = (int) $this->createToken(UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID)->personalAccessToken->getId();
        $this->loginUser(UserFixtures::SUPER_ADMIN_ID);

        $response = $this->requestJson(
            Request::METHOD_PUT,
            sprintf(self::UPDATE_TOOLS_URI_FORMAT, $tokenId),
            ['readTools' => $readTools, 'writeTools' => []],
        );

        self::assertResponseStatusCodeSame($expectedStatusCode);
        if (Response::HTTP_UNPROCESSABLE_ENTITY === $expectedStatusCode) {
            $this->assertValidationErrors($response, ['readTools' => [ValidationException::ERROR_FIELD_INVALID]]);
        }
        $this->assertStoredTools($tokenId, [], $expectedReadTools);
    }

    /**
     * @return iterable<string, array{list<string>, int, list<string>|null}>
     */
    public static function superAdminUpdateToolsDataProvider(): iterable
    {
        yield 'a tool the owner may use' => [[ExampleTool::READ], Response::HTTP_OK, [ExampleTool::READ]];
        yield 'a tool only the super admin may use' => [
            [ExampleTool::RESTRICTED_READ],
            Response::HTTP_UNPROCESSABLE_ENTITY,
            null,
        ];
    }

    public function testUpdateToolsOfANonExistentTokenIsNotFound(): void
    {
        $this->loginUser(UserFixtures::WITHOUT_PERMISSIONS_ID);

        $this->requestJson(
            Request::METHOD_PUT,
            sprintf(self::UPDATE_TOOLS_URI_FORMAT, self::NON_EXISTENT_TOKEN_ID),
            ['readTools' => [], 'writeTools' => []],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    #[DataProvider('routeDataProvider')]
    public function testRequestAuthenticatedByATokenCannotManageTokens(string $method, string $uriFormat, ?array $payload): void
    {
        $token = $this->createToken(UserFixtures::TOKEN_MANAGER_ID, [ExampleTool::WRITE], [ExampleTool::READ]);
        $tokenId = (int) $token->personalAccessToken->getId();
        $this->sendBearerToken($token->token);

        $this->requestJson($method, self::TOKEN_API_PREFIX . sprintf($uriFormat, $tokenId), $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $personalAccessTokens = $this->getRepository()
            ->findByUser($this->getUser(UserFixtures::TOKEN_MANAGER_ID));
        self::assertCount(1, $personalAccessTokens);
        self::assertFalse($personalAccessTokens->first()->isRevoked());
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    #[DataProvider('routeDataProvider')]
    public function testRequestWithoutCredentialsIsUnauthorized(string $method, string $uriFormat, ?array $payload): void
    {
        $this->requestJson($method, sprintf($uriFormat, self::NON_EXISTENT_TOKEN_ID), $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>|null}>
     */
    public static function routeDataProvider(): iterable
    {
        yield 'get_list' => [Request::METHOD_GET, self::URI, null];
        yield 'get_tools' => [Request::METHOD_GET, self::TOOLS_URI, null];
        yield 'create' => [Request::METHOD_POST, self::URI, ['name' => self::TOKEN_NAME]];
        yield 'revoke' => [Request::METHOD_PATCH, self::REVOKE_URI_FORMAT, null];
        yield 'update_tools' => [
            Request::METHOD_PUT,
            self::UPDATE_TOOLS_URI_FORMAT,
            ['readTools' => [ExampleTool::READ], 'writeTools' => []],
        ];
    }

    /**
     * @param list<string> $writeTools
     * @param list<string>|null $readTools
     * @param list<string> $explicitReadTools
     */
    private function createToken(
        int $userId,
        array $writeTools = [],
        ?array $readTools = null,
        array $explicitReadTools = [],
    ): PersonalAccessTokenCreateResult {
        return self::getContainer()->get(PersonalAccessTokenFacade::class)->create(
            user: $this->getUser($userId),
            name: self::TOKEN_NAME,
            writeTools: $writeTools,
            readTools: $readTools,
            explicitReadTools: $explicitReadTools,
        );
    }

    /**
     * @param list<string> $expectedWriteTools
     * @param list<string>|null $expectedReadTools
     */
    private function assertStoredTools(int $tokenId, array $expectedWriteTools, ?array $expectedReadTools): void
    {
        $storedToken = $this->getStoredToken($tokenId);
        self::assertSame($expectedWriteTools, $storedToken->getWriteTools());
        self::assertSame($expectedReadTools, $storedToken->getReadTools());
    }

    private function getStoredToken(int $tokenId): AbstractPersonalAccessToken
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $personalAccessToken = $this->getRepository()
            ->find($tokenId);
        if ($personalAccessToken instanceof AbstractPersonalAccessToken) {
            return $personalAccessToken;
        }

        throw new LogicException(sprintf('Personal access token "%d" is not stored.', $tokenId));
    }

    private function getRepository(): PersonalAccessTokenRepository
    {
        return self::getContainer()->get(PersonalAccessTokenRepository::class);
    }
}
