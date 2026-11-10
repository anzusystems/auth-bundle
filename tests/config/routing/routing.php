<?php

declare(strict_types=1);

use AnzuSystems\AuthBundle\Controller\Api\JsonCredentialsAuthController;
use AnzuSystems\AuthBundle\Controller\Api\OAuth2AuthController;
use AnzuSystems\AuthBundle\Controller\Api\PersonalAccessTokenController;
use AnzuSystems\AuthBundle\Tests\AnzuTestKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import(resource: PersonalAccessTokenController::class, type: 'attribute');

    // A host that exposes the token management behind the firewall of the personal access tokens.
    $routes->import(resource: PersonalAccessTokenController::class, type: 'attribute')
        ->prefix('/token-api')
        ->namePrefix('token_api_');

    $authController = AnzuTestKernel::ENVIRONMENT_OAUTH2 === $routes->env()
        ? OAuth2AuthController::class
        : JsonCredentialsAuthController::class;
    $routes->import(resource: $authController, type: 'attribute')
        ->prefix('/auth');
};
