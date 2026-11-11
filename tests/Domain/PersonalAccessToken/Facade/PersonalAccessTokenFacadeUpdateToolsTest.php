<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Facade;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade\PersonalAccessTokenFacade;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenToolsUpdateDto;
use AnzuSystems\AuthBundle\Tests\AbstractKernelTestCase;
use AnzuSystems\AuthBundle\Tests\Data\Fixtures\UserFixtures;
use AnzuSystems\AuthBundle\Tests\Data\Mcp\ExampleTool;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

final class PersonalAccessTokenFacadeUpdateToolsTest extends AbstractKernelTestCase
{
    private const string EXPIRED_AT = '-1 year';

    public function testUpdateToolsReturnsThePreviousSelection(): void
    {
        $personalAccessToken = $this->createToken(
            userId: UserFixtures::TOKEN_MANAGER_ID,
            writeTools: [ExampleTool::WRITE],
            explicitReadTools: [ExampleTool::EXPLICIT_READ],
        );

        $result = $this->getFacade()
            ->updateTools($personalAccessToken, $this->createDto([ExampleTool::READ], []));

        self::assertSame($personalAccessToken, $result->personalAccessToken);
        self::assertNull($result->previousReadTools);
        self::assertSame([ExampleTool::EXPLICIT_READ], $result->previousExplicitReadTools);
        self::assertSame([ExampleTool::WRITE], $result->previousWriteTools);
        self::assertSame(
            [
                'resource' => $personalAccessToken::getResourceName(),
                'personalAccessTokenId' => $personalAccessToken->getId(),
                'userId' => UserFixtures::TOKEN_MANAGER_ID,
                'previousReadTools' => null,
                'previousExplicitReadTools' => [ExampleTool::EXPLICIT_READ],
                'previousWriteTools' => [ExampleTool::WRITE],
                'readTools' => [ExampleTool::READ],
                'explicitReadTools' => [],
                'writeTools' => [],
            ],
            $result->getLogContext(),
        );
        $storedToken = $this->getStoredToken((int) $personalAccessToken->getId());
        self::assertSame([ExampleTool::READ], $storedToken->getReadTools());
        self::assertSame([], $storedToken->getExplicitReadTools());
        self::assertSame([], $storedToken->getWriteTools());
    }

    /**
     * @param list<string> $readTools
     * @param list<string> $explicitReadTools
     * @param list<string> $writeTools
     */
    #[DataProvider('ownerPermittedToolsDataProvider')]
    public function testUpdateToolsAcceptsToolsTheOwnerMayUse(
        int $ownerId,
        array $readTools,
        array $explicitReadTools,
        array $writeTools,
    ): void {
        $personalAccessToken = $this->createToken($ownerId);

        $this->getFacade()
            ->updateTools($personalAccessToken, $this->createDto(
                readTools: $readTools,
                writeTools: $writeTools,
                explicitReadTools: $explicitReadTools,
            ));

        $storedToken = $this->getStoredToken((int) $personalAccessToken->getId());
        self::assertSame($readTools, $storedToken->getReadTools());
        self::assertSame($explicitReadTools, $storedToken->getExplicitReadTools());
        self::assertSame($writeTools, $storedToken->getWriteTools());
    }

    public function testUpdateToolsOfATokenThatNeverExpires(): void
    {
        $personalAccessToken = $this->getFacade()
            ->create(user: $this->getUser(UserFixtures::TOKEN_MANAGER_ID), name: self::TOKEN_NAME, neverExpires: true)
            ->personalAccessToken;
        self::assertNull($personalAccessToken->getExpiresAt());

        $this->getFacade()
            ->updateTools($personalAccessToken, $this->createDto(
                readTools: [ExampleTool::READ],
                writeTools: [ExampleTool::WRITE],
                explicitReadTools: [ExampleTool::EXPLICIT_READ],
            ));

        $storedToken = $this->getStoredToken((int) $personalAccessToken->getId());
        self::assertNull($storedToken->getExpiresAt());
        self::assertSame([ExampleTool::READ], $storedToken->getReadTools());
        self::assertSame([ExampleTool::EXPLICIT_READ], $storedToken->getExplicitReadTools());
        self::assertSame([ExampleTool::WRITE], $storedToken->getWriteTools());
    }

    /**
     * @return iterable<string, array{int, list<string>, list<string>, list<string>}>
     */
    public static function ownerPermittedToolsDataProvider(): iterable
    {
        yield 'tools granted to the owner' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            [ExampleTool::READ],
            [ExampleTool::EXPLICIT_READ],
            [ExampleTool::WRITE],
        ];
        yield 'every tool for a super admin owner' => [
            UserFixtures::SUPER_ADMIN_ID,
            [ExampleTool::RESTRICTED_READ],
            [ExampleTool::EXPLICIT_READ],
            [ExampleTool::RESTRICTED_WRITE],
        ];
    }

    /**
     * @param list<string> $readTools
     * @param list<string> $explicitReadTools
     * @param list<string> $writeTools
     * @param array<string, list<string>> $expectedErrors
     */
    #[DataProvider('ownerRefusedToolsDataProvider')]
    public function testUpdateToolsChecksThePermissionsOfTheOwner(
        int $ownerId,
        array $readTools,
        array $explicitReadTools,
        array $writeTools,
        array $expectedErrors,
    ): void {
        $personalAccessToken = $this->createToken($ownerId);

        try {
            $this->getFacade()
                ->updateTools($personalAccessToken, $this->createDto(
                    readTools: $readTools,
                    writeTools: $writeTools,
                    explicitReadTools: $explicitReadTools,
                ));
            self::fail('The update was not refused.');
        } catch (ValidationException $exception) {
            self::assertSame($expectedErrors, $exception->getFormattedErrors());
        }
    }

    /**
     * @return iterable<string, array{int, list<string>, list<string>, list<string>, array<string, list<string>>}>
     */
    public static function ownerRefusedToolsDataProvider(): iterable
    {
        yield 'a read tool the owner has no permission for' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            [ExampleTool::RESTRICTED_READ],
            [],
            [],
            ['readTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'a write tool the owner has no permission for' => [
            UserFixtures::READ_TOOLS_TOKEN_MANAGER_ID,
            [],
            [],
            [ExampleTool::RESTRICTED_WRITE],
            ['writeTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'an explicit read tool the owner has no permission for' => [
            UserFixtures::WITHOUT_PERMISSIONS_ID,
            [],
            [ExampleTool::EXPLICIT_READ],
            [],
            ['explicitReadTools' => [ValidationException::ERROR_FIELD_INVALID]],
        ];
        yield 'an owner without any tool permission' => [
            UserFixtures::WITHOUT_PERMISSIONS_ID,
            [ExampleTool::READ],
            [ExampleTool::EXPLICIT_READ],
            [ExampleTool::WRITE],
            [
                'readTools' => [ValidationException::ERROR_FIELD_INVALID],
                'explicitReadTools' => [ValidationException::ERROR_FIELD_INVALID],
                'writeTools' => [ValidationException::ERROR_FIELD_INVALID],
            ],
        ];
    }

    /**
     * @param list<string> $explicitReadTools
     */
    #[DataProvider('refusedUpdateDataProvider')]
    public function testRefusedUpdateLeavesTheEntityUnchanged(array $explicitReadTools, bool $expired): void
    {
        $personalAccessToken = $this->createToken(
            userId: UserFixtures::TOKEN_MANAGER_ID,
            writeTools: [ExampleTool::WRITE],
            readTools: [ExampleTool::READ],
        );
        if ($expired) {
            $personalAccessToken->setExpiresAt(new DateTimeImmutable(self::EXPIRED_AT));
            $this->getEntityManager()
                ->flush();
        }

        try {
            $this->getFacade()
                ->updateTools($personalAccessToken, $this->createDto(
                    readTools: [],
                    writeTools: [],
                    explicitReadTools: $explicitReadTools,
                ));
            self::fail('The update was not refused.');
        } catch (ValidationException) {
            self::assertSame([ExampleTool::READ], $personalAccessToken->getReadTools());
            self::assertSame([], $personalAccessToken->getExplicitReadTools());
            self::assertSame([ExampleTool::WRITE], $personalAccessToken->getWriteTools());
        }

        $this->getEntityManager()
            ->flush();
        $storedToken = $this->getStoredToken((int) $personalAccessToken->getId());
        self::assertSame([ExampleTool::READ], $storedToken->getReadTools());
        self::assertSame([], $storedToken->getExplicitReadTools());
        self::assertSame([ExampleTool::WRITE], $storedToken->getWriteTools());
    }

    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function refusedUpdateDataProvider(): iterable
    {
        yield 'a tool the owner may not grant' => [[ExampleTool::RESTRICTED_READ], false];
        yield 'a tool of another kind' => [[ExampleTool::READ], false];
        yield 'a tool listed twice' => [[ExampleTool::EXPLICIT_READ, ExampleTool::EXPLICIT_READ], false];
        yield 'an expired token' => [[ExampleTool::EXPLICIT_READ], true];
    }

    /**
     * @param list<string>|null $readTools
     * @param list<string>|null $writeTools
     * @param list<string> $explicitReadTools
     */
    private function createDto(
        ?array $readTools,
        ?array $writeTools,
        array $explicitReadTools = [],
    ): PersonalAccessTokenToolsUpdateDto {
        $dto = new PersonalAccessTokenToolsUpdateDto();
        $dto->readTools = $readTools;
        $dto->writeTools = $writeTools;
        $dto->explicitReadTools = $explicitReadTools;

        return $dto;
    }

    private function getFacade(): PersonalAccessTokenFacade
    {
        return self::getContainer()->get(PersonalAccessTokenFacade::class);
    }
}
