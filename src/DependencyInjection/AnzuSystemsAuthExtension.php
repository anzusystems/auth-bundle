<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\DependencyInjection;

use AnzuSystems\AuthBundle\Command\ChangeApiTokenCommand;
use AnzuSystems\AuthBundle\Configuration\OAuth2Configuration;
use AnzuSystems\AuthBundle\Contracts\OAuth2AuthUserRepositoryInterface;
use AnzuSystems\AuthBundle\Contracts\RefreshTokenStorageInterface;
use AnzuSystems\AuthBundle\Controller\Api\JsonCredentialsAuthController;
use AnzuSystems\AuthBundle\Controller\Api\OAuth2AuthController;
use AnzuSystems\AuthBundle\Domain\Process\OAuth2\GrantAccessByOAuth2TokenProcess;
use AnzuSystems\AuthBundle\Domain\Process\OAuth2\ValidateOAuth2AccessTokenProcess;
use AnzuSystems\AuthBundle\HttpClient\OAuth2HttpClient;
use AnzuSystems\AuthBundle\Model\Enum\AuthType;
use AnzuSystems\AuthBundle\RefreshTokenStorage\RedisRefreshTokenStorage;
use AnzuSystems\AuthBundle\Serializer\Handler\Handlers\JwtHandler;
use AnzuSystems\AuthBundle\Util\HttpUtil;
use AnzuSystems\AuthBundle\Util\StatelessTokenUtil;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class AnzuSystemsAuthExtension extends Extension
{
    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $processedConfig = $this->processConfiguration(new Configuration(), $configs);

        $cookieSection = $processedConfig['cookie'];
        $container->setParameter('anzu_systems.auth_bundle.cookie.domain', $cookieSection['domain']);
        $container->setParameter('anzu_systems.auth_bundle.cookie.secure', $cookieSection['secure']);
        $container->setParameter('anzu_systems.auth_bundle.cookie.device_id_name', $cookieSection['device_id_name']);
        $container->setParameter('anzu_systems.auth_bundle.cookie.jwt.payload_part_name', $cookieSection['jwt']['payload_part_name']);
        $container->setParameter('anzu_systems.auth_bundle.cookie.jwt.signature_part_name', $cookieSection['jwt']['signature_part_name']);
        $container->setParameter('anzu_systems.auth_bundle.cookie.refresh_token.name', $cookieSection['refresh_token']['name']);
        $container->setParameter('anzu_systems.auth_bundle.cookie.refresh_token.lifetime', $cookieSection['refresh_token']['lifetime']);
        $container->setParameter('anzu_systems.auth_bundle.cookie.refresh_token.existence_name', $cookieSection['refresh_token']['existence_name']);

        $jwtSection = $processedConfig['jwt'];
        $container->setParameter('anzu_systems.auth_bundle.jwt.audience', $jwtSection['audience']);
        $container->setParameter('anzu_systems.auth_bundle.jwt.algorithm', $jwtSection['algorithm']);
        $container->setParameter('anzu_systems.auth_bundle.jwt.public_cert', $jwtSection['public_cert']);
        $container->setParameter('anzu_systems.auth_bundle.jwt.private_cert', $jwtSection['private_cert'] ?? '');
        $container->setParameter('anzu_systems.auth_bundle.jwt.lifetime', $jwtSection['lifetime']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        $authorizationSection = $processedConfig['authorization'];
        if ($authorizationSection['enabled']) {
            $container
                ->getDefinition(HttpUtil::class)
                ->replaceArgument('$authRedirectDefaultUrl', $authorizationSection['auth_redirect_default_url'])
                ->replaceArgument('$authRedirectQueryUrlAllowedPattern', $authorizationSection['auth_redirect_query_url_allowed_pattern'])
            ;

            $loader->load('authorization.php');

            $redisServiceId = $authorizationSection['refresh_token']['storage']['redis']['service_id'] ?? null;
            $customServiceId = $authorizationSection['refresh_token']['storage']['custom']['service_id'] ?? null;
            if (empty($redisServiceId) && empty($customServiceId)) {
                throw new InvalidArgumentException('Required service_id at path "anzu_systems_auth.authorization.refresh_token.storage.(redis|custom).service_id".');
            }

            if ($redisServiceId) {
                $container
                    ->register(RedisRefreshTokenStorage::class)
                    ->setArgument('$storageRedis', new Reference($authorizationSection['refresh_token']['storage']['redis']['service_id']))
                    ->setAutowired(true)
                    ->setAutoconfigured(true)
                ;
                $container->setAlias(RefreshTokenStorageInterface::class, RedisRefreshTokenStorage::class);
            }

            if ($customServiceId) {
                $container->setAlias(RefreshTokenStorageInterface::class, $customServiceId);
            }

            $authorizationType = AuthType::from($authorizationSection['type']);
            if ($authorizationType->is(AuthType::OAuth2)) {
                $oauth2Section = $authorizationSection['oauth2'];
                $container
                    ->register(OAuth2Configuration::class)
                    ->setArgument('$ssoAccessTokenUrl', $oauth2Section['access_token_url'])
                    ->setArgument('$ssoAuthorizeUrl', $oauth2Section['authorize_url'])
                    ->setArgument('$ssoRedirectUrl', $oauth2Section['redirect_url'])
                    ->setArgument('$ssoUserInfoUrl', $oauth2Section['user_info_url'])
                    ->setArgument('$ssoUserInfoByEmailUrl', $oauth2Section['user_info_by_email_url'])
                    ->setArgument('$ssoUserInfoClass', $oauth2Section['user_info_class'])
                    ->setArgument('$ssoClientId', $oauth2Section['client_id'])
                    ->setArgument('$ssoClientSecret', $oauth2Section['client_secret'])
                    ->setArgument('$ssoPublicCert', $oauth2Section['public_cert'])
                    ->setArgument('$ssoScopes', $oauth2Section['scopes'])
                    ->setArgument('$ssoScopeDelimiter', $oauth2Section['scope_delimiter'])
                    ->setArgument('$considerAccessTokenAsJwt', $oauth2Section['consider_access_token_as_jwt'])
                    ->setArgument('$accessTokenCachePool', new Reference($oauth2Section['access_token_cache']))
                ;

                $container
                    ->setAlias(OAuth2AuthUserRepositoryInterface::class, $oauth2Section['user_repository_service_id']);

                $container
                    ->register(ValidateOAuth2AccessTokenProcess::class)
                    ->setAutowired(true)
                    ->setAutoconfigured(true)
                ;

                $container
                    ->register(GrantAccessByOAuth2TokenProcess::class)
                    ->setAutowired(true)
                    ->setAutoconfigured(true)
                    ->setArgument('$authMethod', $oauth2Section['auth_method'])
                ;

                $container
                    ->register(OAuth2AuthController::class)
                    ->setAutowired(true)
                    ->setAutoconfigured(true)
                ;

                $container
                    ->register(StatelessTokenUtil::class)
                    ->setArgument('$statelessTokenSalt', $oauth2Section['state_token_salt'])
                    ->setArgument('$enabled', $oauth2Section['state_token_enabled'])
                ;

                $container
                    ->register(OAuth2HttpClient::class)
                    ->setAutowired(true)
                ;

                $container
                    ->register(JwtHandler::class)
                    ->setAutowired(true)
                    ->setAutoconfigured(true)
                ;
            }
            if ($authorizationType->is(AuthType::JsonCredentials)) {
                $container
                    ->register(JsonCredentialsAuthController::class)
                    ->setAutowired(true)
                    ->setAutoconfigured(true)
                ;
            }
        }

        if (class_exists(EntityManagerInterface::class)) {
            $container
                ->register(ChangeApiTokenCommand::class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
            ;
        }
    }
}
