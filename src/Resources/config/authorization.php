<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\AuthBundle\Domain\Process\GrantAccessOnResponseProcess;
use AnzuSystems\AuthBundle\Domain\Process\RefreshTokenProcess;
use AnzuSystems\AuthBundle\Event\Listener\LogoutListener;
use AnzuSystems\AuthBundle\Security\AuthenticationFailureHandler;
use AnzuSystems\AuthBundle\Security\AuthenticationSuccessHandler;
use AnzuSystems\AuthBundle\Security\Http\EventListener\UserProviderListener;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

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

    $services
        ->set(UserProviderListener::class)
        ->args([
            service('security.user_providers'),
        ])
        ->tag('kernel.event_listener', ['event' => CheckPassportEvent::class, 'priority' => 1024, 'method' => 'checkPassport'])
    ;
};
