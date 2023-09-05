<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\DependencyInjection;

use AnzuSystems\AuthBundle\Configuration\OAuth2Configuration;
use AnzuSystems\AuthBundle\DependencyInjection\AnzuSystemsAuthExtension;
use AnzuSystems\AuthBundle\Domain\Process\GrantAccessOnResponseProcess;
use AnzuSystems\AuthBundle\Domain\Process\RefreshTokenProcess;
use AnzuSystems\AuthBundle\Event\Listener\LogoutListener;
use AnzuSystems\AuthBundle\Security\AuthenticationFailureHandler;
use AnzuSystems\AuthBundle\Security\AuthenticationSuccessHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Parser;

final class AnzuSystemsAuthExtensionTest extends TestCase
{
    private ?ContainerBuilder $configuration;

    protected function tearDown(): void
    {
        $this->configuration = null;
    }

    public function testEmptyConfiguration(): void
    {
        $this->configuration = new ContainerBuilder();
        $loader = new AnzuSystemsAuthExtension();
        $config = $this->getEmptyConfig();
        $loader->load([$config], $this->configuration);

        $this->assertParameter(null, 'anzu_systems.auth_bundle.cookie.domain');
        $this->assertParameter(true, 'anzu_systems.auth_bundle.cookie.secure');
        $this->assertParameter('anz_di', 'anzu_systems.auth_bundle.cookie.device_id_name');
        $this->assertParameter('anz_jp', 'anzu_systems.auth_bundle.cookie.jwt.payload_part_name');
        $this->assertParameter('anz_js', 'anzu_systems.auth_bundle.cookie.jwt.signature_part_name');
        $this->assertParameter('anz_js', 'anzu_systems.auth_bundle.cookie.jwt.signature_part_name');

        $this->assertParameter('anz_rt', 'anzu_systems.auth_bundle.cookie.refresh_token.name');
        $this->assertParameter(31536000, 'anzu_systems.auth_bundle.cookie.refresh_token.lifetime');
        $this->assertParameter('anz_rte', 'anzu_systems.auth_bundle.cookie.refresh_token.existence_name');

        $this->assertParameter('anz', 'anzu_systems.auth_bundle.jwt.audience');
        $this->assertParameter('ES256', 'anzu_systems.auth_bundle.jwt.algorithm');
        $this->assertParameter('foo_public_cert', 'anzu_systems.auth_bundle.jwt.public_cert');
        $this->assertParameter('foo_private_cert', 'anzu_systems.auth_bundle.jwt.private_cert');
        $this->assertParameter(3600, 'anzu_systems.auth_bundle.jwt.lifetime');

        $this->assertNotHasDefinition(AuthenticationSuccessHandler::class);
        $this->assertNotHasDefinition(AuthenticationFailureHandler::class);
        $this->assertNotHasDefinition(GrantAccessOnResponseProcess::class);
        $this->assertNotHasDefinition(RefreshTokenProcess::class);
        $this->assertNotHasDefinition(LogoutListener::class);
        $this->assertNotHasDefinition(OAuth2Configuration::class);
    }

    public function testFullConfiguration(): void
    {
        $this->configuration = new ContainerBuilder();
        $loader = new AnzuSystemsAuthExtension();
        $config = $this->getFullConfig();
        $loader->load([$config], $this->configuration);

        $this->assertParameter('.example.com', 'anzu_systems.auth_bundle.cookie.domain');
        $this->assertParameter(true, 'anzu_systems.auth_bundle.cookie.secure');
        $this->assertParameter('anz_di', 'anzu_systems.auth_bundle.cookie.device_id_name');
        $this->assertParameter('anz_jp', 'anzu_systems.auth_bundle.cookie.jwt.payload_part_name');
        $this->assertParameter('anz_js', 'anzu_systems.auth_bundle.cookie.jwt.signature_part_name');
        $this->assertParameter('anz_js', 'anzu_systems.auth_bundle.cookie.jwt.signature_part_name');

        $this->assertParameter('anz_rt', 'anzu_systems.auth_bundle.cookie.refresh_token.name');
        $this->assertParameter(31536000, 'anzu_systems.auth_bundle.cookie.refresh_token.lifetime');
        $this->assertParameter('anz_rte', 'anzu_systems.auth_bundle.cookie.refresh_token.existence_name');

        $this->assertParameter('anz', 'anzu_systems.auth_bundle.jwt.audience');
        $this->assertParameter('ES256', 'anzu_systems.auth_bundle.jwt.algorithm');
        $this->assertParameter('foo_public_cert', 'anzu_systems.auth_bundle.jwt.public_cert');
        $this->assertParameter('foo_private_cert', 'anzu_systems.auth_bundle.jwt.private_cert');
        $this->assertParameter(3600, 'anzu_systems.auth_bundle.jwt.lifetime');


        $this->assertHasDefinition(AuthenticationSuccessHandler::class);
        $this->assertHasDefinition(AuthenticationFailureHandler::class);
        $this->assertHasDefinition(GrantAccessOnResponseProcess::class);
        $this->assertHasDefinition(RefreshTokenProcess::class);
        $this->assertHasDefinition(LogoutListener::class);

        $this->assertHasDefinition(OAuth2Configuration::class);

        $oAuth2ConfigurationDefinition = $this->configuration->getDefinition(OAuth2Configuration::class);
        $arguments = $oAuth2ConfigurationDefinition->getArguments();
        self::assertSame('https://example.com/access-token-url', $arguments['$ssoAccessTokenUrl']);
        self::assertSame('https://example.com/authorize-url', $arguments['$ssoAuthorizeUrl']);
        self::assertSame('https://example.com/redirect-url', $arguments['$ssoRedirectUrl']);
        self::assertSame('https://example.com/user-info-url', $arguments['$ssoUserInfoUrl']);
        self::assertSame('AnzuSystems\AuthBundle\Model\SsoUserDto', $arguments['$ssoUserInfoClass']);
        self::assertSame('qux', $arguments['$ssoClientId']);
        self::assertSame('bar-secret', $arguments['$ssoClientSecret']);
        self::assertSame('qux-public-cert', $arguments['$ssoPublicCert']);
        self::assertSame(['email', 'profile'], $arguments['$ssoScopes']);
        self::assertSame(' ', $arguments['$ssoScopeDelimiter']);
    }

    private function getEmptyConfig(): ?array
    {
        $yaml = <<<EOF
cookie:
    secure: true
jwt:
    public_cert: 'foo_public_cert'
    private_cert: 'foo_private_cert'
EOF;
        $parser = new Parser();

        return $parser->parse($yaml);
    }

    private function getFullConfig(): array
    {
        $yaml = <<<EOF
cookie:
    domain: .example.com
    secure: true
jwt:
    public_cert: 'foo_public_cert'
    private_cert: 'foo_private_cert'
authorization:
    enabled: true
    refresh_token:
      storage:
        redis:
          service_id: SharedTokenStorageRedis
    auth_redirect_default_url: 'https://example.com/redirect-url'
    auth_redirect_query_url_allowed_pattern: '.+'
    type: oauth2
    oauth2:
      user_repository_service_id: App\Repository\UserRepository
      authorize_url: 'https://example.com/authorize-url'
      user_info_url: 'https://example.com/user-info-url'
      state_token_salt: 'qux-quux'
      access_token_url: 'https://example.com/access-token-url'
      redirect_url: 'https://example.com/redirect-url'
      client_id: qux
      client_secret: 'bar-secret'
      public_cert: 'qux-public-cert'
      scopes:
        - email
        - profile
      scope_delimiter: ' '   
EOF;
        $parser = new Parser();

        return $parser->parse($yaml);
    }

    private function assertParameter($value, string $key): void
    {
        self::assertSame($value, $this->configuration->getParameter($key), sprintf('%s parameter is correct', $key));
    }

    private function assertHasDefinition(string $id): void
    {
        self::assertTrue(($this->configuration->hasDefinition($id) || $this->configuration->hasAlias($id)));
    }

    private function assertNotHasDefinition(string $id): void
    {
        self::assertFalse(($this->configuration->hasDefinition($id) || $this->configuration->hasAlias($id)));
    }
}
