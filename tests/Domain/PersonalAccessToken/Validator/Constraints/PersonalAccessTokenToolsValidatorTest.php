<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Domain\PersonalAccessToken\Validator\Constraints;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenToolResolver;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints\PersonalAccessTokenTools;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints\PersonalAccessTokenToolsValidator;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\Contracts\Entity\AnzuUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PersonalAccessTokenToolsValidatorTest extends TestCase
{
    private const string FIREWALL_NAME = 'api';
    private const string READ_TOOL = 'list_published_articles';
    private const string WRITE_TOOL = 'create_quiz';
    private const string NOT_PERMITTED_TOOL = 'update_robots_txt';
    private const string UNKNOWN_TOOL = 'delete_article';
    private const string READ_PERMISSION = 'cms_article_read';
    private const string WRITE_PERMISSION = 'cms_quiz_create';
    private const string NOT_PERMITTED_PERMISSION = 'cms_robotsTxt_update';

    /**
     * @param list<string>|null $tools
     * @param list<string> $expectedMessages
     */
    #[DataProvider('toolsDataProvider')]
    public function testOnlyToolsTheCreatorMayUseAreValid(
        bool $mcpEnabled,
        bool $authenticated,
        ?array $tools,
        array $expectedMessages,
    ): void {
        $violations = $this->createValidator(mcpEnabled: $mcpEnabled, authenticated: $authenticated)
            ->validate($tools, new PersonalAccessTokenTools());

        self::assertSame(
            $expectedMessages,
            array_map(
                static fn (ConstraintViolationInterface $violation): string => (string) $violation->getMessage(),
                iterator_to_array($violations),
            ),
        );
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: list<string>|null, 3: list<string>}>
     */
    public static function toolsDataProvider(): array
    {
        return [
            'no narrowing' => [true, true, null, []],
            'permitted read and write tools' => [true, true, [self::READ_TOOL, self::WRITE_TOOL], []],
            'tool without permission' => [true, true, [self::READ_TOOL, self::NOT_PERMITTED_TOOL], [ValidationException::ERROR_FIELD_INVALID]],
            'unknown tool' => [true, true, [self::UNKNOWN_TOOL], [ValidationException::ERROR_FIELD_INVALID]],
            'no narrowing without mcp' => [false, true, null, []],
            'tool without mcp' => [false, true, [self::READ_TOOL], [ValidationException::ERROR_FIELD_INVALID]],
            'console: configured tool without permission' => [true, false, [self::READ_TOOL, self::NOT_PERMITTED_TOOL], []],
            'console: unknown tool' => [true, false, [self::READ_TOOL, self::UNKNOWN_TOOL], [ValidationException::ERROR_FIELD_INVALID]],
            'console: tool without mcp' => [false, false, [self::READ_TOOL], [ValidationException::ERROR_FIELD_INVALID]],
        ];
    }

    private function createValidator(bool $mcpEnabled, bool $authenticated): ValidatorInterface
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(
                static fn (mixed $attribute): bool => in_array(needle: $attribute, haystack: [self::READ_PERMISSION, self::WRITE_PERMISSION], strict: true),
            );
        $toolAccessChecker = new McpToolAccessChecker(
            toolPermissions: [
                self::READ_TOOL => self::READ_PERMISSION,
                self::WRITE_TOOL => self::WRITE_PERMISSION,
                self::NOT_PERMITTED_TOOL => self::NOT_PERMITTED_PERMISSION,
            ],
            security: $security,
            readOnlyToolNames: [self::READ_TOOL],
        );
        $toolResolver = $mcpEnabled
            ? new PersonalAccessTokenToolResolver($toolAccessChecker)
            : new PersonalAccessTokenToolResolver();
        $tokenStorage = new TokenStorage();
        if ($authenticated) {
            $tokenStorage->setToken(new PostAuthenticationToken(
                user: $this->createStub(AnzuUser::class),
                firewallName: self::FIREWALL_NAME,
                roles: [],
            ));
        }

        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                PersonalAccessTokenToolsValidator::class => new PersonalAccessTokenToolsValidator($toolResolver, $tokenStorage),
            ]))
            ->getValidator();
    }
}
