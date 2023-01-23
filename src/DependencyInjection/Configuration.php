<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\DependencyInjection;

use AnzuSystems\AuthBundle\Configuration\OAuth2Configuration;
use AnzuSystems\AuthBundle\Model\Enum\AuthType;
use AnzuSystems\AuthBundle\Model\Enum\JwtAlgorithm;
use AnzuSystems\AuthBundle\Model\SsoUserDto;
use Exception;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @throws Exception
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('anzu_systems_auth');

        $treeBuilder->getRootNode()
            ->children()
                ->append($this->addCookieSection())
                ->append($this->addJwtSection())
                ->append($this->addAuthorizationSection())
            ->end()
        ;

        return $treeBuilder;
    }

    private function addCookieSection(): NodeDefinition
    {
        return (new TreeBuilder('cookie'))->getRootNode()
            ->isRequired()
            ->children()
                ->scalarNode('domain')->isRequired()->cannotBeEmpty()->end()
                ->booleanNode('secure')->isRequired()->end()
                ->scalarNode('device_id_name')->defaultValue('anz_di')->end()
                ->arrayNode('jwt')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('payload_part_name')->isRequired()->defaultValue('anz_jp')->end()
                        ->scalarNode('signature_part_name')->isRequired()->defaultValue('anz_js')->end()
                    ->end()
                ->end()
                ->arrayNode('refresh_token')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('lifetime')->defaultValue(31_536_000)->min(7_200)->end() // 1 year default
                        ->scalarNode('name')->defaultValue('anz_rt')->end()
                        ->scalarNode('existence_name')->defaultValue('anz_rte')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addJwtSection(): NodeDefinition
    {
        return (new TreeBuilder('jwt'))->getRootNode()
            ->isRequired()
            ->children()
                ->scalarNode('audience')->defaultValue('anz')->cannotBeEmpty()->end()
                ->enumNode('algorithm')
                    ->values(JwtAlgorithm::values())
                    ->defaultValue(JwtAlgorithm::Default->toString())
                ->end()
                ->scalarNode('public_cert')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('private_cert')->isRequired()->end()
                ->integerNode('lifetime')->defaultValue(3_600)->min(3_600)->end() // 1 hour
            ->end()
        ;
    }

    /**
     * @throws Exception
     */
    private function addAuthorizationSection(): NodeDefinition
    {
        return (new TreeBuilder('authorization'))->getRootNode()
            ->canBeEnabled()
            ->children()
                ->enumNode('type')
                    ->values(AuthType::values())
                    ->defaultValue(AuthType::Default->toString())
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('refresh_token')
                    ->children()
                        ->arrayNode('storage')
                            ->children()
                                ->arrayNode('redis')
                                    ->children()
                                        ->scalarNode('service_id')->end()
                                    ->end()
                                ->end()
                                ->arrayNode('custom')
                                    ->children()
                                        ->scalarNode('service_id')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('auth_redirect_default_url')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('auth_redirect_query_url_allowed_pattern')->isRequired()->cannotBeEmpty()->end()
                ->append($this->addOAuth2AuthorizationSection())
            ->end()
        ;
    }

    /**
     * @throws Exception
     */
    private function addOAuth2AuthorizationSection(): NodeDefinition
    {
        return (new TreeBuilder('oauth2'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('user_repository_service_id')->defaultValue('')->end()
                ->scalarNode('state_token_salt')->defaultValue(bin2hex(random_bytes(32)))->end()
                ->booleanNode('state_token_enabled')->defaultTrue()->end()
                ->scalarNode('authorize_url')->defaultValue('')->end()
                ->scalarNode('access_token_url')->defaultValue('')->end()
                ->scalarNode('user_info_url')
                    ->defaultValue('')
                    ->info(sprintf(
                        'You can use placeholder "%s", which will be replaced with user identifier.',
                        OAuth2Configuration::SSO_USER_ID_PLACEHOLDER_URL,
                    ))
                ->end()
                ->scalarNode('access_token_cache')
                    ->cannotBeEmpty()
                    ->defaultValue('cache.app')
                    ->info('A cache for storing access tokens.')
                ->end()
                ->scalarNode('user_info_class')
                    ->defaultValue(SsoUserDto::class)
                    ->info('Any replacement of the default value class, must extend it.')
                ->end()
                ->scalarNode('redirect_url')->defaultValue('')->end()
                ->scalarNode('client_id')->defaultValue('')->end()
                ->scalarNode('client_secret')->defaultValue('')->end()
                ->scalarNode('public_cert')->defaultValue('')->end()
                ->arrayNode('scopes')->scalarPrototype()->end()->end()
            ->end()
        ;
    }
}
