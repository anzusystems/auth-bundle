<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Command;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Facade\PersonalAccessTokenFacade;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu:personal-access-token:revoke',
    description: 'Revoke a personal access token of any user, it stops working at once'
)]
final class RevokePersonalAccessTokenCommand extends Command
{
    public function __construct(
        private readonly PersonalAccessTokenRepository $personalAccessTokenRepo,
        private readonly PersonalAccessTokenFacade $personalAccessTokenFacade,
    ) {
        parent::__construct();
    }

    /**
     * @throws AppReadOnlyModeException
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Id of the token, from anzu:personal-access-token:list.')]
        int $id,
    ): int {
        AnzuApp::throwOnReadOnlyMode();

        $token = $this->personalAccessTokenRepo->find($id);
        if (false === ($token instanceof AbstractPersonalAccessToken)) {
            $io->writeln(sprintf('<error>Personal access token (%d) not found.</error>', $id));

            return self::FAILURE;
        }
        if ($token->isRevoked()) {
            $io->writeln(sprintf('Token %d "%s" is already revoked.', $id, $token->getName()));

            return self::SUCCESS;
        }

        $this->personalAccessTokenFacade->revoke($token);
        $io->writeln(sprintf(
            'Revoked token %d "%s" of user %d.',
            $id,
            $token->getName(),
            (int) $token->getUser()->getId(),
        ));

        return self::SUCCESS;
    }
}
