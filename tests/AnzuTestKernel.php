<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests;

use AnzuSystems\AuthBundle\AnzuSystemsAuthBundle;
use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\Kernel\AnzuKernel;
use AnzuSystems\SerializerBundle\AnzuSystemsSerializerBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class AnzuTestKernel extends AnzuKernel
{
    /**
     * The authorization type is fixed when the container is built, so each type has its own environment:
     * "test" logs in by JSON credentials, this one by OAuth2.
     */
    public const string ENVIRONMENT_OAUTH2 = 'test_oauth2';

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new AnzuSystemsSerializerBundle();
        yield new MonologBundle();
        yield new AnzuSystemsCommonBundle();
        yield new AnzuSystemsAuthBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__ . '/config/{packages}/*.php');
        $container->import(__DIR__ . '/config/{packages}/' . $this->environment . '/*.php');
        $container->import(__DIR__ . '/config/{services}/*.php');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/config/routing/routing.php');
    }
}
