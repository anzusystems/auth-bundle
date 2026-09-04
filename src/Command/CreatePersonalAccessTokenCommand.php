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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzu:personal-access-token:create',
    description: 'Create a personal access token for a user and print the plaintext token (shown only once)'
)]
final class CreatePersonalAccessTokenCommand extends Command
{
    private const string ARG_USER_ID = 'userId';
    private const string OPTION_NAME = 'name';
    private const string OPTION_EXPIRES_AT = 'expires-at';
    private const string OPTION_NEVER_EXPIRES = 'never-expires';
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

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARG_USER_ID, InputArgument::REQUIRED, 'Id of the user owning the token.')
            ->addOption(self::OPTION_NAME, null, InputOption::VALUE_REQUIRED, 'Token label, e.g. "mcp-agent".')
            ->addOption(self::OPTION_EXPIRES_AT, null, InputOption::VALUE_REQUIRED, sprintf(
                'Expiration date time, e.g. "2026-09-21 12:00:00". Defaults to %s, at most %s.',
                AbstractPersonalAccessToken::DEFAULT_EXPIRES_AT_DATE,
                AbstractPersonalAccessToken::MAX_EXPIRES_AT_DATE,
            ))
            ->addOption(self::OPTION_NEVER_EXPIRES, null, InputOption::VALUE_NONE, sprintf(
                'Create a token without expiration (mutually exclusive with --%s).',
                self::OPTION_EXPIRES_AT,
            ))
        ;
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws RandomException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        AnzuApp::throwOnReadOnlyMode();

        $userId = (int) $input->getArgument(self::ARG_USER_ID);
        $user = $this->entityManager->find($this->userEntityClass, $userId);
        if (false === ($user instanceof AnzuUser)) {
            $output->writeln(sprintf('<error>User (%d) not found.</error>', $userId));

            return self::FAILURE;
        }

        $name = (string) $input->getOption(self::OPTION_NAME);
        if (StringHelper::isEmpty($name)) {
            $output->writeln(sprintf('<error>Option --%s is required.</error>', self::OPTION_NAME));

            return self::FAILURE;
        }

        $neverExpires = (bool) $input->getOption(self::OPTION_NEVER_EXPIRES);
        if ($neverExpires && StringHelper::isNotEmpty((string) $input->getOption(self::OPTION_EXPIRES_AT))) {
            $output->writeln(sprintf(
                '<error>Options --%s and --%s are mutually exclusive.</error>',
                self::OPTION_EXPIRES_AT,
                self::OPTION_NEVER_EXPIRES,
            ));

            return self::FAILURE;
        }

        try {
            $expiresAt = $this->resolveExpiresAtOption($input);
        } catch (Exception) {
            $output->writeln(sprintf(
                '<error>Invalid --%s value "%s", provide a date time, e.g. "2027-07-09 12:00:00".</error>',
                self::OPTION_EXPIRES_AT,
                (string) $input->getOption(self::OPTION_EXPIRES_AT),
            ));

            return self::FAILURE;
        }

        try {
            $result = $this->personalAccessTokenFacade->create(
                user: $user,
                name: $name,
                expiresAt: $expiresAt,
                neverExpires: $neverExpires,
            );
        } catch (ValidationException $exception) {
            $output->writeln(sprintf(
                '<error>Validation failed: %s</error>',
                (string) json_encode($exception->getFormattedErrors()),
            ));

            return self::FAILURE;
        }

        $output->writeln(sprintf('Token (shown only once): <info>%s</info>', $result->token));
        $output->writeln(sprintf(
            'Expires at: %s',
            $result->personalAccessToken->getExpiresAt()?->format(self::DATETIME_FORMAT) ?? self::EXPIRES_AT_NEVER,
        ));

        return self::SUCCESS;
    }

    private function resolveExpiresAtOption(InputInterface $input): ?DateTimeImmutable
    {
        $expiresAtOption = (string) $input->getOption(self::OPTION_EXPIRES_AT);
        if (StringHelper::isEmpty($expiresAtOption)) {
            return null;
        }

        return new DateTimeImmutable($expiresAtOption);
    }
}
