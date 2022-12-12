<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Command;

use AnzuSystems\AuthBundle\Contracts\ApiTokenUserInterface;
use AnzuSystems\CommonBundle\Helper\PasswordHelper;
use AnzuSystems\Contracts\Entity\AnzuUser;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'anzusystems:token:change')]
final class ChangeApiTokenCommand extends Command
{
    public const ARG_USER_ID = 'userId';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Change API token for system user.')
            ->addArgument(
                self::ARG_USER_ID,
                InputArgument::REQUIRED,
                'ID of user to change API token.'
            )
            ->addOption(
                'token',
                null,
                InputOption::VALUE_OPTIONAL,
                'Raw token value that will be hashed.'
            )
        ;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $newToken = $input->getOption('token') ?: bin2hex(random_bytes(32));
        $userId = (int) $input->getArgument(self::ARG_USER_ID);
        $user = $this->entityManager->find(AnzuUser::class, $userId);
        if (false === ($user instanceof AnzuUser)) {
            throw new LogicException(sprintf('User (%d) not found!', $userId));
        }
        if (false === ($user instanceof ApiTokenUserInterface)) {
            throw new LogicException(sprintf(
                'User class must implement interface "%s"!',
                ApiTokenUserInterface::class,
            ));
        }

        $user->setApiToken(PasswordHelper::passwordHash($newToken));
        $this->entityManager->flush();
        $output->writeln('New token is: ' . $newToken);

        return self::SUCCESS;
    }
}
