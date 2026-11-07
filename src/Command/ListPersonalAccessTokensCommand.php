<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Command;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu:personal-access-token:list',
    description: 'List the personal access tokens of a user, without the tokens themselves'
)]
final class ListPersonalAccessTokensCommand extends Command
{
    private const array HEADERS = ['Id', 'Name', 'Status', 'Expires at', 'Last used at', 'Read tools', 'Write tools'];
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_EXPIRED = 'expired';
    public const string STATUS_REVOKED = 'revoked';
    private const string NO_VALUE = '-';
    private const string LIST_SEPARATOR = ', ';
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @param class-string<AnzuUser> $userEntityClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PersonalAccessTokenRepository $personalAccessTokenRepo,
        private readonly string $userEntityClass,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Id of the user owning the tokens.')]
        int $userId,
    ): int {
        $user = $this->entityManager->find($this->userEntityClass, $userId);
        if (false === ($user instanceof AnzuUser)) {
            $io->writeln(sprintf('<error>User (%d) not found.</error>', $userId));

            return self::FAILURE;
        }

        $io->table(self::HEADERS, $this->personalAccessTokenRepo->findByUser($user)->map(
            fn (AbstractPersonalAccessToken $token): array => [
                $token->getId(),
                $token->getName(),
                $this->resolveStatus($token),
                $this->formatDate($token->getExpiresAt()),
                $this->formatDate($token->getLastUsedAt()),
                $this->formatReadTools($token->getReadTools()),
                $this->formatTools($token->getWriteTools()),
            ]
        )->toArray());

        return self::SUCCESS;
    }

    private function resolveStatus(AbstractPersonalAccessToken $token): string
    {
        if ($token->isRevoked()) {
            return self::STATUS_REVOKED;
        }
        $expiresAt = $token->getExpiresAt();
        if ($expiresAt instanceof DateTimeImmutable && $expiresAt <= AnzuApp::date()) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_ACTIVE;
    }

    private function formatDate(?DateTimeImmutable $date): string
    {
        return $date?->format(self::DATETIME_FORMAT) ?? self::NO_VALUE;
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
