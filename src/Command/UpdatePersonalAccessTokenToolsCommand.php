<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Command;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade\PersonalAccessTokenFacade;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Model\PersonalAccessTokenToolsUpdateDto;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenToolResolver;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu:personal-access-token:update-tools',
    description: 'Replace the MCP tools of a personal access token of any user, the change applies from the next request'
)]
final class UpdatePersonalAccessTokenToolsCommand extends Command
{
    public const string MESSAGE_NOTHING_CHANGED = 'Nothing changed.';
    public const string MESSAGE_REMOVED_WHEN_SAVED = 'Not among the tools the owner may grant, removed when saved: %s';
    public const string MESSAGE_NOT_ACTIVE = 'Token %d "%s" is revoked or expired, its tools cannot be changed.';
    public const string MESSAGE_NO_TOOL_OPTION = 'Name the tools with --read-tool, --explicit-read-tool, --write-tool, --no-read-tools or --all-read-tools, or run the command in a terminal.';
    public const string MESSAGE_EXCLUSIVE_READ_OPTIONS = 'Options --read-tool, --no-read-tools and --all-read-tools are mutually exclusive.';
    public const string QUESTION_SAVE = 'Save the new tools?';
    public const string READ_SCOPE_ALL = 'all';
    public const string READ_SCOPE_SELECTED = 'selected';
    public const string READ_SCOPE_NONE = 'none';
    public const string LABEL_READ_TOOLS = 'Read tools';
    public const string LABEL_EXPLICIT_READ_TOOLS = 'Explicit read tools';
    public const string LABEL_WRITE_TOOLS = 'Write tools';

    private const array READ_SCOPES = [
        self::READ_SCOPE_ALL => CreatePersonalAccessTokenCommand::READ_TOOLS_UNRESTRICTED . ', also tools added later',
        self::READ_SCOPE_SELECTED => 'only the tools chosen in the next question',
        self::READ_SCOPE_NONE => 'no read tool',
    ];
    private const string QUESTION_READ_SCOPE = 'Which read tools may the token use?';
    private const string QUESTION_TOOLS_FORMAT = '%s (numbers or names separated by a comma)';
    private const string CHANGE_FORMAT = '%s: %s (before: %s)';
    private const string ERROR_FORMAT = '<error>%s</error>';
    private const string CHOICE_SEPARATOR = ',';
    private const string LIST_SEPARATOR = ', ';
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';
    private const string EXPIRES_AT_NEVER = 'never';

    public function __construct(
        private readonly PersonalAccessTokenRepository $personalAccessTokenRepo,
        private readonly PersonalAccessTokenFacade $personalAccessTokenFacade,
        private readonly PersonalAccessTokenToolResolver $toolResolver,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    /**
     * @param list<string> $writeTools
     * @param list<string> $readTools
     * @param list<string> $explicitReadTools
     *
     * @throws AppReadOnlyModeException
     */
    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Argument(description: 'Id of the token, from anzu:personal-access-token:list.')]
        int $id,
        #[Option(
            description: 'Name of an MCP write tool the token may call, repeat the option to grant more tools. Without it the token becomes read only.',
            name: 'write-tool',
        )]
        array $writeTools = [],
        #[Option(
            description: 'Name of an MCP read tool the token may use, repeat the option to allow more tools. Without it the token gets every read tool its user may use, except the tools granted only by name.',
            name: 'read-tool',
        )]
        array $readTools = [],
        #[Option(
            description: 'Name of an MCP read tool granted only by name, repeat the option to grant more tools. Without it the token gets none of them.',
            name: 'explicit-read-tool',
        )]
        array $explicitReadTools = [],
        #[Option(description: 'Give the token no read tool (mutually exclusive with --read-tool and --all-read-tools).')]
        bool $noReadTools = false,
        #[Option(description: 'Give the token every read tool its user may use, except the tools granted only by name (mutually exclusive with --read-tool and --no-read-tools).')]
        bool $allReadTools = false,
    ): int {
        AnzuApp::throwOnReadOnlyMode();

        $token = $this->personalAccessTokenRepo->find($id);
        if (false === ($token instanceof AbstractPersonalAccessToken)) {
            $io->writeln(sprintf('<error>Personal access token (%d) not found.</error>', $id));

            return self::FAILURE;
        }
        $readOptions = array_filter([$noReadTools, $allReadTools, false === empty($readTools)]);
        if (count($readOptions) > 1) {
            $io->writeln(sprintf(self::ERROR_FORMAT, self::MESSAGE_EXCLUSIVE_READ_OPTIONS));

            return self::FAILURE;
        }

        $dto = new PersonalAccessTokenToolsUpdateDto();
        $dto->writeTools = $writeTools;
        $dto->explicitReadTools = $explicitReadTools;
        $dto->readTools = empty($readTools) ? null : $readTools;
        if ($noReadTools) {
            $dto->readTools = [];
        }

        $hasToolOption = $noReadTools
            || $allReadTools
            || false === empty($writeTools)
            || false === empty($readTools)
            || false === empty($explicitReadTools);
        if (false === $hasToolOption) {
            if ($input->isInteractive()) {
                if ($token->isRevoked() || $token->isExpired()) {
                    $io->writeln(sprintf(self::ERROR_FORMAT, sprintf(self::MESSAGE_NOT_ACTIVE, $id, $token->getName())));

                    return self::FAILURE;
                }
                $dto = $this->askToolsUpdate($io, $token);
            }
            // Symfony turns interaction off only for -n and -q; without a terminal QuestionHelper does it when the input ends.
            if (false === $input->isInteractive()) {
                $io->writeln(sprintf(self::ERROR_FORMAT, self::MESSAGE_NO_TOOL_OPTION));

                return self::FAILURE;
            }
        }
        if (null === $dto) {
            $io->writeln(self::MESSAGE_NOTHING_CHANGED);

            return self::SUCCESS;
        }

        try {
            $result = $this->personalAccessTokenFacade->updateTools($token, $dto);
        } catch (ValidationException $exception) {
            $io->writeln(sprintf(
                '<error>Validation failed: %s</error>',
                (string) json_encode($exception->getFormattedErrors()),
            ));

            return self::FAILURE;
        }

        $this->logger->info(PersonalAccessTokenFacade::LOG_MESSAGE_TOOLS_UPDATED, $result->getLogContext());
        $io->writeln(sprintf(
            'Updated tools of token %d "%s" of user %d.',
            $id,
            $token->getName(),
            (int) $token->getUser()->getId(),
        ));
        $this->writeChange(
            io: $io,
            label: self::LABEL_READ_TOOLS,
            tools: $this->formatReadTools($token->getReadTools()),
            previousTools: $this->formatReadTools($result->previousReadTools),
        );
        $this->writeChange(
            io: $io,
            label: self::LABEL_EXPLICIT_READ_TOOLS,
            tools: $this->formatTools($token->getExplicitReadTools()),
            previousTools: $this->formatTools($result->previousExplicitReadTools),
        );
        $this->writeChange(
            io: $io,
            label: self::LABEL_WRITE_TOOLS,
            tools: $this->formatTools($token->getWriteTools()),
            previousTools: $this->formatTools($result->previousWriteTools),
        );

        return self::SUCCESS;
    }

    private function askToolsUpdate(SymfonyStyle $io, AbstractPersonalAccessToken $token): ?PersonalAccessTokenToolsUpdateDto
    {
        $this->writeToken($io, $token);

        $grantableToolNames = $this->toolResolver->resolveGrantableToolNamesForUser($token->getUser());
        $readOffer = $this->filterGrantable($this->toolResolver->resolveReadToolNames(), $grantableToolNames);
        $explicitReadOffer = $this->filterGrantable($this->toolResolver->resolveExplicitReadToolNames(), $grantableToolNames);
        $writeOffer = $this->filterGrantable($this->toolResolver->resolveWriteToolNames(), $grantableToolNames);
        $removedToolNames = [
            ...array_diff($token->getReadTools() ?? [], $readOffer),
            ...array_diff($token->getExplicitReadTools(), $explicitReadOffer),
            ...array_diff($token->getWriteTools(), $writeOffer),
        ];
        if (false === empty($removedToolNames)) {
            $io->writeln(sprintf(
                '<comment>' . self::MESSAGE_REMOVED_WHEN_SAVED . '</comment>',
                implode(self::LIST_SEPARATOR, $removedToolNames),
            ));
        }

        $readTools = $this->askReadTools($io, $token->getReadTools(), $readOffer);
        $explicitReadTools = $this->askTools(
            io: $io,
            label: self::LABEL_EXPLICIT_READ_TOOLS,
            offer: $explicitReadOffer,
            currentTools: $token->getExplicitReadTools(),
        );
        $writeTools = $this->askTools(
            io: $io,
            label: self::LABEL_WRITE_TOOLS,
            offer: $writeOffer,
            currentTools: $token->getWriteTools(),
        );
        $isSameSelection = $this->isSameTools($readTools, $token->getReadTools())
            && $this->isSameTools($explicitReadTools, $token->getExplicitReadTools())
            && $this->isSameTools($writeTools, $token->getWriteTools());
        if ($isSameSelection) {
            return null;
        }

        $this->writeChange(
            io: $io,
            label: self::LABEL_READ_TOOLS,
            tools: $this->formatReadTools($readTools),
            previousTools: $this->formatReadTools($token->getReadTools()),
        );
        $this->writeChange(
            io: $io,
            label: self::LABEL_EXPLICIT_READ_TOOLS,
            tools: $this->formatTools($explicitReadTools),
            previousTools: $this->formatTools($token->getExplicitReadTools()),
        );
        $this->writeChange(
            io: $io,
            label: self::LABEL_WRITE_TOOLS,
            tools: $this->formatTools($writeTools),
            previousTools: $this->formatTools($token->getWriteTools()),
        );
        if (false === $io->confirm(self::QUESTION_SAVE, false)) {
            return null;
        }

        $dto = new PersonalAccessTokenToolsUpdateDto();
        $dto->readTools = $readTools;
        $dto->explicitReadTools = $explicitReadTools;
        $dto->writeTools = $writeTools;

        return $dto;
    }

    private function writeToken(SymfonyStyle $io, AbstractPersonalAccessToken $token): void
    {
        $user = $token->getUser();
        $io->writeln(sprintf(
            'Token %d "%s" of user %d (%s), expires at: %s',
            (int) $token->getId(),
            $token->getName(),
            (int) $user->getId(),
            $user->getEmail(),
            $token->getExpiresAt()?->format(self::DATETIME_FORMAT) ?? self::EXPIRES_AT_NEVER,
        ));
        $io->writeln(sprintf('%s: %s', self::LABEL_READ_TOOLS, $this->formatReadTools($token->getReadTools())));
        $io->writeln(sprintf('%s: %s', self::LABEL_EXPLICIT_READ_TOOLS, $this->formatTools($token->getExplicitReadTools())));
        $io->writeln(sprintf('%s: %s', self::LABEL_WRITE_TOOLS, $this->formatTools($token->getWriteTools())));
    }

    /**
     * @param list<string>|null $readTools
     * @param list<string> $readOffer
     *
     * @return list<string>|null
     */
    private function askReadTools(SymfonyStyle $io, ?array $readTools, array $readOffer): ?array
    {
        $scope = $io->choice(
            question: self::QUESTION_READ_SCOPE,
            choices: self::READ_SCOPES,
            default: $this->resolveReadScope($readTools, $readOffer),
        );
        if (self::READ_SCOPE_ALL === $scope) {
            return null;
        }
        if (self::READ_SCOPE_NONE === $scope) {
            return [];
        }

        return $this->askTools(
            io: $io,
            label: self::LABEL_READ_TOOLS,
            offer: $readOffer,
            currentTools: $readTools ?? [],
        );
    }

    /**
     * @param list<string>|null $readTools
     * @param list<string> $readOffer
     */
    private function resolveReadScope(?array $readTools, array $readOffer): string
    {
        if (null === $readTools) {
            return self::READ_SCOPE_ALL;
        }
        if (empty(array_intersect($readOffer, $readTools))) {
            return self::READ_SCOPE_NONE;
        }

        return self::READ_SCOPE_SELECTED;
    }

    /**
     * The first choice is "none": a multiselect question takes no empty answer.
     * The default answer names the choices by their numbers: the prompt of a multiselect question reads only those.
     *
     * @param list<string> $offer
     * @param list<string> $currentTools
     *
     * @return list<string>
     */
    private function askTools(SymfonyStyle $io, string $label, array $offer, array $currentTools): array
    {
        if (empty($offer)) {
            return [];
        }

        $choices = [CreatePersonalAccessTokenCommand::TOOLS_NONE, ...$offer];
        $currentChoiceNumbers = array_intersect($choices, $currentTools) |> array_keys(...);
        $default = CreatePersonalAccessTokenCommand::TOOLS_NONE;
        if (false === empty($currentChoiceNumbers)) {
            $default = implode(self::CHOICE_SEPARATOR, $currentChoiceNumbers);
        }
        /** @var list<string> $answer */
        $answer = $io->choice(
            question: sprintf(self::QUESTION_TOOLS_FORMAT, $label),
            choices: $choices,
            default: $default,
            multiSelect: true,
        );

        return array_intersect($offer, $answer) |> array_values(...);
    }

    /**
     * @param list<string> $toolNames
     * @param list<string> $grantableToolNames
     *
     * @return list<string>
     */
    private function filterGrantable(array $toolNames, array $grantableToolNames): array
    {
        return array_intersect($toolNames, $grantableToolNames) |> array_values(...);
    }

    /**
     * @param list<string>|null $tools
     * @param list<string>|null $otherTools
     */
    private function isSameTools(?array $tools, ?array $otherTools): bool
    {
        if (null === $tools || null === $otherTools) {
            return $tools === $otherTools;
        }
        sort($tools);
        sort($otherTools);

        return $tools === $otherTools;
    }

    private function writeChange(SymfonyStyle $io, string $label, string $tools, string $previousTools): void
    {
        $io->writeln(sprintf(self::CHANGE_FORMAT, $label, $tools, $previousTools));
    }

    /**
     * @param list<string>|null $readTools
     */
    private function formatReadTools(?array $readTools): string
    {
        if (null === $readTools) {
            return CreatePersonalAccessTokenCommand::READ_TOOLS_UNRESTRICTED;
        }

        return $this->formatTools($readTools);
    }

    /**
     * @param list<string> $tools
     */
    private function formatTools(array $tools): string
    {
        if (empty($tools)) {
            return CreatePersonalAccessTokenCommand::TOOLS_NONE;
        }

        return implode(self::LIST_SEPARATOR, $tools);
    }
}
