<?php

declare(strict_types=1);

use AnzuSystems\AuthBundle\Tests\AnzuTestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');

$kernel = new AnzuTestKernel(
    appNamespace: 'anzusystems',
    appSystem: 'authbundle',
    appVersion: 'dev',
    appReadOnlyMode: false,
    environment: 'test',
    debug: true,
);
$kernel->boot();

$app = new Application($kernel);
$app->setAutoExit(false);

$databasePath = $kernel->getContainer()
    ->get('doctrine')
    ->getConnection()
    ->getParams()['path'];
new Filesystem()->remove($databasePath);

$commands = [
    ['command' => 'doctrine:schema:create'],
    ['command' => 'anzusystems:fixtures:generate'],
];
foreach ($commands as $command) {
    $input = new ArrayInput($command);
    $input->setInteractive(false);
    $output = new BufferedOutput();
    if (Command::SUCCESS !== $app->run($input, $output)) {
        throw new RuntimeException(sprintf('The test database setup failed at "%s": %s', $command['command'], $output->fetch()));
    }
}

$kernel->shutdown();
