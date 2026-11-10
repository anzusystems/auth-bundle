<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\AuthBundle\Tests\AnzuTestKernel;
use AnzuSystems\AuthBundle\Tests\Data\Fixtures\UserFixtures;
use AnzuSystems\AuthBundle\Tests\Data\Mcp\ExampleTool;
use AnzuSystems\AuthBundle\Tests\Data\RefreshTokenStorage\InMemoryRefreshTokenStorage;
use AnzuSystems\AuthBundle\Tests\Data\Repository\UserRepository;
use AnzuSystems\AuthBundle\Tests\Data\Security\ExampleToolVoter;
use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolDefinition;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Redis;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    // common-bundle requires a Redis service for its locks; nothing here takes a lock, so it never connects.
    $services->set('TestRedis', Redis::class);

    $services->set(UserRepository::class)
        ->arg('$registry', service(ManagerRegistry::class))
        ->tag('doctrine.repository_service')
    ;

    $services->set(UserFixtures::class)
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE)
    ;

    $services->set(InMemoryRefreshTokenStorage::class)
        ->public()
    ;

    $services->set(ExampleToolVoter::class)
        ->autowire()
        ->autoconfigure()
    ;

    $toolDefinitions = [];
    foreach (ExampleTool::READ_ONLY_BY_NAME as $toolName => $readOnly) {
        $toolDefinitions[$toolName] = inline_service(McpToolDefinition::class)
            ->arg('$permission', $toolName)
            ->arg('$readOnly', $readOnly)
            ->arg('$category', ExampleTool::CATEGORY)
            ->arg('$explicitGrant', ExampleTool::EXPLICIT_READ === $toolName)
        ;
    }
    $services->set(McpToolDefinitions::class)
        ->arg('$definitions', $toolDefinitions)
    ;

    $services->set(McpToolAccessChecker::class)
        ->autowire()
    ;

    if (AnzuTestKernel::ENVIRONMENT_OAUTH2 === $configurator->env()) {
        $services->set(MockHttpClient::class)
            ->public()
        ;
        $services->alias(HttpClientInterface::class, MockHttpClient::class);
    }
};
