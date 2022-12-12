<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\AuthBundle\Domain\Process\GrantAccessOnResponseProcess;
use AnzuSystems\AuthBundle\Domain\Process\RefreshTokenProcess;
use AnzuSystems\AuthBundle\Event\Listener\LogoutListener;
use AnzuSystems\AuthBundle\Security\AuthenticationFailureHandler;
use AnzuSystems\AuthBundle\Security\AuthenticationSuccessHandler;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services
        ->set(AuthenticationSuccessHandler::class)
        ->autowire()
    ;

    $services
        ->set(AuthenticationFailureHandler::class)
        ->autowire()
    ;

    $services
        ->set(GrantAccessOnResponseProcess::class)
        ->autowire()
    ;

    $services
        ->set(RefreshTokenProcess::class)
        ->autowire()
    ;

    $services
        ->set(LogoutListener::class)
        ->autoconfigure()
        ->autowire()
    ;
};
