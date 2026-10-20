<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Validator\Constraints;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenWriteToolResolver;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints\PersonalAccessTokenWriteTools;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints\PersonalAccessTokenWriteToolsValidator;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PersonalAccessTokenWriteToolsValidatorTest extends TestCase
{
    private const string READ_TOOL = 'list_sites';
    private const string WRITE_TOOL = 'create_quiz';
    private const string UNKNOWN_TOOL = 'delete_site';
    private const string READ_PERMISSION = 'cms_site_read';
    private const string WRITE_PERMISSION = 'cms_quiz_create';

    /**
     * @param list<string> $writeTools
     * @param list<string> $expectedMessages
     */
    #[DataProvider('writeToolsDataProvider')]
    public function testOnlyConfiguredWriteToolsAreValid(bool $mcpEnabled, array $writeTools, array $expectedMessages): void
    {
        $violations = $this->createValidator($mcpEnabled)
            ->validate($writeTools, new PersonalAccessTokenWriteTools());

        self::assertSame(
            $expectedMessages,
            array_map(
                static fn (ConstraintViolationInterface $violation): string => (string) $violation->getMessage(),
                iterator_to_array($violations),
            ),
        );
    }

    /**
     * @return array<string, array{0: bool, 1: list<string>, 2: list<string>}>
     */
    public static function writeToolsDataProvider(): array
    {
        return [
            'write tool' => [true, [self::WRITE_TOOL], []],
            'unknown tool' => [true, [self::WRITE_TOOL, self::UNKNOWN_TOOL], [ValidationException::ERROR_FIELD_INVALID]],
            'read tool' => [true, [self::READ_TOOL], [ValidationException::ERROR_FIELD_INVALID]],
            'write tool without mcp' => [false, [self::WRITE_TOOL], [ValidationException::ERROR_FIELD_INVALID]],
        ];
    }

    private function createValidator(bool $mcpEnabled): ValidatorInterface
    {
        $toolAccessChecker = new McpToolAccessChecker(
            toolPermissions: [
                self::READ_TOOL => self::READ_PERMISSION,
                self::WRITE_TOOL => self::WRITE_PERMISSION,
            ],
            security: $this->createStub(Security::class),
            readOnlyToolNames: [self::READ_TOOL],
        );
        $writeToolResolver = $mcpEnabled
            ? new PersonalAccessTokenWriteToolResolver($toolAccessChecker)
            : new PersonalAccessTokenWriteToolResolver();

        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                PersonalAccessTokenWriteToolsValidator::class => new PersonalAccessTokenWriteToolsValidator($writeToolResolver),
            ]))
            ->getValidator();
    }
}
