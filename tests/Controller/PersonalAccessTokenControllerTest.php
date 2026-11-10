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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PersonalAccessTokenControllerTest extends AbstractControllerTestCase
{
    private const string URI = '/personal-access-token';
    private const string TOOLS_URI = self::URI . '/tools';
    private const string REVOKE_URI_FORMAT = self::URI . '/%d/revoke';
    private const string TOKEN_API_PREFIX = '/token-api';
    private const string REVOKED_AT = '2020-01-01T00:00:00';
    private const string TOKEN_NAME = 'Example token';
    private const int NON_EXISTENT_TOKEN_ID = 999_999;

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $expectedWriteTools
     * @param list<string>|null $expectedReadTools
     */
    #[DataProvider('createDataProvider')]
    public function testCreate(int $userId, array $payload, array $expectedWriteTools, ?array $expectedReadTools): void
    {
        $this->loginUser($userId);

        $response = $this->requestJson(Request::METHOD_POST, self::URI, $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertStringStartsWith(AbstractPersonalAccessToken::TOKEN_PREFIX, $response['token']);
        self::assertSame(self::TOKEN_NAME, $response['personalAccessToken']['name']);
        self::assertSame($userId, $response['personalAccessToken']['user']);
        self::assertSame($expectedWriteTools, $response['personalAccessToken']['writeTools']);
        self::assertSame($expectedReadTools, $response['personalAccessToken']['readTools']);
        $personalAccessToken = $this->getRepository()
            ->findOneActiveByTokenHash(AbstractPersonalAccessToken::hashToken($response['token']));
        self::assertSame($response['personalAccessToken']['id'], $personalAccessToken?->getId());
    }

    /**
     * @return iterable<string, array{int, array<string, mixed>, list<string>, list<string>|null}>
     */
    public static function createDataProvider(): iterable
    {
        yield 'only a name, as sent before the tool lists existed' => [
            UserFixtures::TOKEN_MANAGER_ID,
            ['name' => self::TOKEN_NAME],
            [],
            null,
        ];
        yield 'write and read tools with createWithWriteTools' => [
            UserFixtures::TOKEN_MANAGER_ID,
            ['name' => self::TOKEN_NAME, 'writeTools' => [ExampleTool::WRITE], 'readTools' => [ExampleTool::READ]],
            [ExampleTool::WRITE],
            [ExampleTool::READ],
        ];
        yield 'read tools without createWithWriteTools' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            ['name' => self::TOKEN_NAME, 'readTools' => [ExampleTool::READ, ExampleTool::EXPLICIT_READ]],
            [],
            [ExampleTool::READ, ExampleTool::EXPLICIT_READ],
        ];
        yield 'no tools without createWithWriteTools' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            ['name' => self::TOKEN_NAME],
            [],
            null,
        ];
        yield 'an empty write tool list without createWithWriteTools' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            ['name' => self::TOKEN_NAME, 'writeTools' => [], 'readTools' => []],
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
        yield 'a blank name' => [
            ['name' => ''],
            ['name' => [ValidationException::ERROR_FIELD_EMPTY]],
        ];
    }

    public function testGetList(): void
    {
        $olderToken = $this->createToken(UserFixtures::TOKEN_MANAGER_ID);
        $newerToken = $this->createToken(UserFixtures::TOKEN_MANAGER_ID, [ExampleTool::WRITE], [ExampleTool::READ]);
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
    }

    /**
     * @param list<string> $writeTools
     * @param list<string>|null $readTools
     */
    private function createToken(int $userId, array $writeTools = [], ?array $readTools = null): PersonalAccessTokenCreateResult
    {
        return self::getContainer()->get(PersonalAccessTokenFacade::class)->create(
            user: $this->getUser($userId),
            name: self::TOKEN_NAME,
            writeTools: $writeTools,
            readTools: $readTools,
        );
    }

    private function getRepository(): PersonalAccessTokenRepository
    {
        return self::getContainer()->get(PersonalAccessTokenRepository::class);
    }
}
