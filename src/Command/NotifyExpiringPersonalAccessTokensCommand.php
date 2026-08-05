<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Command;

use AnzuSystems\AuthBundle\Contracts\PersonalAccessTokenExpiryNotifierInterface;
use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Repository\PersonalAccessTokenRepository;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    'anzu:personal-access-token:notify-expiring',
    'Notify owners of personal access tokens expiring in 7 days or in 1 day.'
)]
final class NotifyExpiringPersonalAccessTokensCommand extends Command
{
    private const int EARLY_NOTICE_DAYS = 7;
    private const int FINAL_NOTICE_DAYS = 1;
    private const array NOTIFY_DAYS_REMAINING = [self::EARLY_NOTICE_DAYS, self::FINAL_NOTICE_DAYS];
    private const string WINDOW_LENGTH = '+1 day';

    public function __construct(
        private readonly PersonalAccessTokenRepository $personalAccessTokenRepo,
        private readonly PersonalAccessTokenExpiryNotifierInterface $expiryNotifier,
    ) {
        parent::__construct();
    }

    /**
     * @throws AppReadOnlyModeException
     */
    public function __invoke(SymfonyStyle $io): int
    {
        AnzuApp::throwOnReadOnlyMode();

        $notifiedCount = 0;
        foreach (self::NOTIFY_DAYS_REMAINING as $daysRemaining) {
            $notifiedCount += $this->notifyWindow($daysRemaining);
        }

        $io->writeln(sprintf('Dispatched %d expiry notification(s).', $notifiedCount));

        return Command::SUCCESS;
    }

    private function notifyWindow(int $daysRemaining): int
    {
        $dayStart = AnzuApp::date(sprintf('+%d days', $daysRemaining))->setTime(0, 0);
        $from = $dayStart;
        if (self::FINAL_NOTICE_DAYS === $daysRemaining) {
            $from = AnzuApp::date();
        }
        $tokens = $this->personalAccessTokenRepo->findAllActiveExpiringBetween(
            from: $from,
            until: $dayStart->modify(self::WINDOW_LENGTH),
        );
        $notifiedCount = 0;
        foreach ($tokens as $personalAccessToken) {
            if ($this->expiryNotifier->notifyExpiring($personalAccessToken, $daysRemaining)) {
                ++$notifiedCount;
            }
        }

        return $notifiedCount;
    }
}
