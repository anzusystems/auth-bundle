<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\AuthBundle\Command\ChangeApiTokenCommand;
use AnzuSystems\AuthBundle\Configuration\CookieConfiguration;
use AnzuSystems\AuthBundle\Configuration\JwtConfiguration;
use AnzuSystems\AuthBundle\Security\Authentication\ApiTokenAuthenticator;
use AnzuSystems\AuthBundle\Security\Authentication\JwtAuthentication;
use AnzuSystems\AuthBundle\Util\HttpUtil;
use AnzuSystems\AuthBundle\Util\JwtUtil;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services
        ->set(CookieConfiguration::class)
        ->arg('$domain', param('anzu_systems.auth_bundle.cookie.domain'))
        ->arg('$sameSite', param('anzu_systems.auth_bundle.cookie.same_site'))
        ->arg('$secure', param('anzu_systems.auth_bundle.cookie.secure'))
        ->arg('$deviceIdCookieName', param('anzu_systems.auth_bundle.cookie.device_id_name'))
        ->arg('$jwtPayloadCookieName', param('anzu_systems.auth_bundle.cookie.jwt.payload_part_name'))
        ->arg('$jwtSignatureCookieName', param('anzu_systems.auth_bundle.cookie.jwt.signature_part_name'))
        ->arg('$refreshTokenCookieName', param('anzu_systems.auth_bundle.cookie.refresh_token.name'))
        ->arg('$refreshTokenExistenceCookieName', param('anzu_systems.auth_bundle.cookie.refresh_token.existence_name'))
        ->arg('$refreshTokenLifetime', param('anzu_systems.auth_bundle.cookie.refresh_token.lifetime'))
    ;

    $services
        ->set(JwtConfiguration::class)
        ->arg('$audience', param('anzu_systems.auth_bundle.jwt.audience'))
        ->arg('$algorithm', param('anzu_systems.auth_bundle.jwt.algorithm'))
        ->arg('$publicCert', param('anzu_systems.auth_bundle.jwt.public_cert'))
        ->arg('$privateCert', param('anzu_systems.auth_bundle.jwt.private_cert'))
        ->arg('$lifetime', param('anzu_systems.auth_bundle.jwt.lifetime'))
    ;

    $services
        ->set(JwtUtil::class)
        ->autowire()
    ;

    $services
        ->set(HttpUtil::class)
        ->arg('$authRedirectDefaultUrl', '')
        ->arg('$authRedirectQueryUrlAllowedPattern', '')
        ->autowire()
    ;

    $services
        ->set(JwtAuthentication::class)
        ->autowire()
    ;

    $services
        ->set(ApiTokenAuthenticator::class)
        ->autowire()
    ;

    $services
        ->set(ChangeApiTokenCommand::class)
        ->tag('console.command')
        ->autowire()
        ->autoconfigure()
    ;
};
