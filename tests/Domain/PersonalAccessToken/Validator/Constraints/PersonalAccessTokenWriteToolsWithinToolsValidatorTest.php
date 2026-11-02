<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Validator\Constraints;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints\PersonalAccessTokenWriteToolsWithinTools;
use AnzuSystems\AuthBundle\Tests\Data\Entity\PersonalAccessToken;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\AnzuApp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class PersonalAccessTokenWriteToolsWithinToolsValidatorTest extends TestCase
{
    private const string READ_TOOL = 'list_published_articles';
    private const string WRITE_TOOL = 'create_quiz';

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

    /**
     * @param list<string> $writeTools
     * @param list<string>|null $tools
     * @param array<string, string> $expectedViolations
     */
    #[DataProvider('writeToolsDataProvider')]
    public function testWriteToolsMustBeWithinTools(array $writeTools, ?array $tools, array $expectedViolations): void
    {
        $personalAccessToken = new PersonalAccessToken()
            ->setWriteTools($writeTools)
            ->setTools($tools);

        $violations = Validation::createValidator()
            ->validate($personalAccessToken, new PersonalAccessTokenWriteToolsWithinTools());

        $actualViolations = [];
        foreach ($violations as $violation) {
            $actualViolations[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }
        self::assertSame($expectedViolations, $actualViolations);
    }

    /**
     * @return array<string, array{0: list<string>, 1: list<string>|null, 2: array<string, string>}>
     */
    public static function writeToolsDataProvider(): array
    {
        return [
            'write tool without narrowed tools' => [[self::WRITE_TOOL], null, []],
            'write tool within tools' => [[self::WRITE_TOOL], [self::READ_TOOL, self::WRITE_TOOL], []],
            'read only token with narrowed tools' => [[], [self::READ_TOOL], []],
            'write tool outside tools' => [[self::WRITE_TOOL], [self::READ_TOOL], ['writeTools' => ValidationException::ERROR_FIELD_INVALID]],
        ];
    }
}
