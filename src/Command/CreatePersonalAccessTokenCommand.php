<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Command;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade\PersonalAccessTokenFacade;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Random\RandomException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu:personal-access-token:create',
    description: 'Create a personal access token for a user and print the plaintext token (shown only once)'
)]
final class CreatePersonalAccessTokenCommand extends Command
{
    private const string LIST_SEPARATOR = ', ';
    private const string WRITE_TOOLS_NONE = 'none, the token is read only';
    private const string READ_TOOLS_NONE = 'none';
    private const string READ_TOOLS_UNRESTRICTED = 'all the user may use';
    private const string EXPIRES_AT_NEVER = 'never';
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @param class-string<AnzuUser> $userEntityClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PersonalAccessTokenFacade $personalAccessTokenFacade,
        private readonly string $userEntityClass,
    ) {
        parent::__construct();
    }

    /**
     * @param list<string> $writeTools
     * @param list<string> $readTools
     *
     * @throws AppReadOnlyModeException
     * @throws RandomException
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Id of the user owning the token.')]
        int $userId,
        #[Option(description: 'Token label, e.g. "mcp-agent".')]
        string $name = '',
        #[Option(description: 'Expiration date time, e.g. "2026-09-21 12:00:00". Defaults to '
            . AbstractPersonalAccessToken::DEFAULT_EXPIRES_AT_DATE . ', at most '
            . AbstractPersonalAccessToken::MAX_EXPIRES_AT_DATE . '.')]
        string $expiresAt = '',
        #[Option(description: 'Create a token without expiration (mutually exclusive with --expires-at).')]
        bool $neverExpires = false,
        #[Option(
            description: 'Name of an MCP write tool the token may call, repeat the option to grant more tools. Without it the token is read only.',
            name: 'write-tool',
        )]
        array $writeTools = [],
        #[Option(
            description: 'Name of an MCP read tool the token may use, repeat the option to allow more tools. Without it the token keeps every read tool its user may use.',
            name: 'read-tool',
        )]
        array $readTools = [],
        #[Option(description: 'Give the token no read tool (mutually exclusive with --read-tool).')]
        bool $noReadTools = false,
    ): int {
        AnzuApp::throwOnReadOnlyMode();

        $user = $this->entityManager->find($this->userEntityClass, $userId);
        if (false === ($user instanceof AnzuUser)) {
            $io->writeln(sprintf('<error>User (%d) not found.</error>', $userId));

            return self::FAILURE;
        }
        if (StringHelper::isEmpty($name)) {
            $io->writeln('<error>Option --name is required.</error>');

            return self::FAILURE;
        }
        if ($neverExpires && StringHelper::isNotEmpty($expiresAt)) {
            $io->writeln('<error>Options --expires-at and --never-expires are mutually exclusive.</error>');

            return self::FAILURE;
        }
        if ($noReadTools && false === empty($readTools)) {
            $io->writeln('<error>Options --read-tool and --no-read-tools are mutually exclusive.</error>');

            return self::FAILURE;
        }

        try {
            $expiresAtDate = StringHelper::isEmpty($expiresAt) ? null : new DateTimeImmutable($expiresAt);
        } catch (Exception) {
            $io->writeln(sprintf(
                '<error>Invalid --expires-at value "%s", provide a date time, e.g. "2027-07-09 12:00:00".</error>',
                $expiresAt,
            ));

            return self::FAILURE;
        }

        $resolvedReadTools = empty($readTools) ? null : $readTools;
        if ($noReadTools) {
            $resolvedReadTools = [];
        }

        try {
            $result = $this->personalAccessTokenFacade->create(
                user: $user,
                name: $name,
                expiresAt: $expiresAtDate,
                neverExpires: $neverExpires,
                writeTools: $writeTools,
                readTools: $resolvedReadTools,
            );
        } catch (ValidationException $exception) {
            $io->writeln(sprintf(
                '<error>Validation failed: %s</error>',
                (string) json_encode($exception->getFormattedErrors()),
            ));

            return self::FAILURE;
        }

        $token = $result->personalAccessToken;
        $io->writeln(sprintf('Token (shown only once): <info>%s</info>', $result->token));
        $io->writeln(sprintf(
            'Expires at: %s',
            $token->getExpiresAt()?->format(self::DATETIME_FORMAT) ?? self::EXPIRES_AT_NEVER,
        ));
        $io->writeln(sprintf('Write tools: %s', $this->formatWriteTools($token->getWriteTools())));
        $io->writeln(sprintf('Read tools: %s', $this->formatReadTools($token->getReadTools())));

        return self::SUCCESS;
    }

    /**
     * @param list<string> $writeTools
     */
    private function formatWriteTools(array $writeTools): string
    {
        if (empty($writeTools)) {
            return self::WRITE_TOOLS_NONE;
        }

        return implode(self::LIST_SEPARATOR, $writeTools);
    }

    /**
     * @param list<string>|null $readTools
     */
    private function formatReadTools(?array $readTools): string
    {
        if (null === $readTools) {
            return self::READ_TOOLS_UNRESTRICTED;
        }
        if (empty($readTools)) {
            return self::READ_TOOLS_NONE;
        }

        return implode(self::LIST_SEPARATOR, $readTools);
    }
}
